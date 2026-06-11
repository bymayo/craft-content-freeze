<?php

namespace bymayo\craftcontentfreeze\controllers;

use bymayo\craftcontentfreeze\models\Freeze;
use bymayo\craftcontentfreeze\Plugin;

use Craft;
use craft\helpers\DateTimeHelper;
use craft\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class FreezesController extends Controller
{
    protected array|bool|int $allowAnonymous = false;

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        // Gated by Craft's auto-registered "Access Content Freeze" permission
        // (accessPlugin-content-freeze). Admins always pass.
        $this->requirePermission('accessPlugin-content-freeze');

        return true;
    }

    public function actionIndex(): Response
    {
        $freezes = Plugin::getInstance()->freezes->getAllFreezes();

        // Active freezes first, then ordered by start date (open-ended first).
        usort($freezes, function (Freeze $a, Freeze $b) {
            $aActive = $a->getStatus() === Freeze::STATUS_ACTIVE;
            $bActive = $b->getStatus() === Freeze::STATUS_ACTIVE;

            if ($aActive !== $bActive) {
                return $aActive ? -1 : 1;
            }

            return ($a->dateFrom?->getTimestamp() ?? PHP_INT_MIN) <=> ($b->dateFrom?->getTimestamp() ?? PHP_INT_MIN);
        });

        return $this->renderTemplate('content-freeze/freezes/_index', [
            'freezes' => $freezes,
        ]);
    }

    public function actionEdit(?int $freezeId = null, ?Freeze $freeze = null): Response
    {
        if ($freeze === null) {
            if ($freezeId !== null) {
                $freeze = Plugin::getInstance()->freezes->getFreezeById($freezeId);

                if ($freeze === null) {
                    throw new NotFoundHttpException('Freeze not found.');
                }
            } else {
                $freeze = new Freeze();
            }
        }

        return $this->renderTemplate('content-freeze/freezes/_edit', [
            'freeze' => $freeze,
            'isNew' => !$freeze->id,
            'settings' => Plugin::getInstance()->getSettings(),
        ]);
    }

    public function actionSave(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $freezeId = $request->getBodyParam('freezeId');

        if ($freezeId) {
            $freeze = Plugin::getInstance()->freezes->getFreezeById((int) $freezeId);

            if ($freeze === null) {
                throw new NotFoundHttpException('Freeze not found.');
            }
        } else {
            $freeze = new Freeze();
        }

        $freeze->name = $request->getBodyParam('name', $freeze->name);
        $freeze->description = (string) $request->getBodyParam('description', '');
        $freeze->enabled = (bool) $request->getBodyParam('enabled', false);
        $freeze->notifyUsers = (bool) $request->getBodyParam('notifyUsers', false);
        $freeze->dateFrom = DateTimeHelper::toDateTime($request->getBodyParam('dateFrom')) ?: null;
        $freeze->dateTo = DateTimeHelper::toDateTime($request->getBodyParam('dateTo')) ?: null;
        $freeze->showNoticePane = (bool) $request->getBodyParam('showNoticePane', false);
        $freeze->noticePaneHeading = (string) $request->getBodyParam('noticePaneHeading', '');
        $freeze->noticePaneText = (string) $request->getBodyParam('noticePaneText', '');
        $freeze->showNoticeBar = (bool) $request->getBodyParam('showNoticeBar', false);
        $freeze->noticeBarText = (string) $request->getBodyParam('noticeBarText', '');
        $freeze->userGroups = $request->getBodyParam('userGroups', []);

        // Moving users into a target group grants them that group's permissions,
        // so only allow targets the current user is permitted to assign. Without
        // this, the "Access Content Freeze" permission would be a privilege-
        // escalation path (a non-admin could route users into a higher group).
        $unauthorized = $this->unauthorizedTargetGroups($freeze->userGroups);

        if (!empty($unauthorized)) {
            Craft::$app->getSession()->setError(Craft::t('content-freeze', 'You don’t have permission to move users into: {groups}.', [
                'groups' => implode(', ', $unauthorized),
            ]));

            return $this->renderTemplate('content-freeze/freezes/_edit', [
                'freeze' => $freeze,
                'isNew' => !$freeze->id,
                'settings' => Plugin::getInstance()->getSettings(),
            ]);
        }

        // A freeze (target) group must belong to a single source group, across
        // all freezes. Sharing one would corrupt the restore: lifting the freeze
        // moves every member of the target group back to just one source.
        $conflicts = $this->conflictingTargetGroups($freeze);

        if (!empty($conflicts)) {
            Craft::$app->getSession()->setError(Craft::t('content-freeze', 'The “Move Users To” group {groups} is already in use for a different user group. Pick a separate group for each one so users can be restored correctly.', [
                'groups' => implode(', ', $conflicts),
            ]));

            return $this->renderTemplate('content-freeze/freezes/_edit', [
                'freeze' => $freeze,
                'isNew' => !$freeze->id,
                'settings' => Plugin::getInstance()->getSettings(),
            ]);
        }

        if (!Plugin::getInstance()->freezes->saveFreeze($freeze)) {
            Craft::$app->getSession()->setError(Craft::t('content-freeze', 'Couldn’t save freeze.'));

            return $this->renderTemplate('content-freeze/freezes/_edit', [
                'freeze' => $freeze,
                'isNew' => !$freeze->id,
                'settings' => Plugin::getInstance()->getSettings(),
            ]);
        }

        // Apply the change immediately rather than waiting for the next request.
        Plugin::getInstance()->freezes->reconcile();

        Craft::$app->getSession()->setNotice(Craft::t('content-freeze', 'Freeze saved.'));

        return $this->redirectToPostedUrl($freeze);
    }

    public function actionDelete(): Response
    {
        $this->requirePostRequest();

        $freezeId = (int) Craft::$app->getRequest()->getRequiredBodyParam('id');

        $freezes = Plugin::getInstance()->freezes;

        // Capture the freeze's group mapping before deleting it - once it's gone
        // it's no longer in the universe, so we feed its mapping into reconcile()
        // to make sure its users are moved back out of the freeze group.
        $freeze = $freezes->getFreezeById($freezeId);
        $extraUniverse = $freeze !== null ? $freezes->getFreezeMapping($freeze) : [];

        $freezes->deleteFreezeById($freezeId);
        $freezes->reconcile(true, $extraUniverse);

        Craft::$app->getSession()->setNotice(Craft::t('content-freeze', 'Freeze deleted.'));

        return $this->redirect('content-freeze');
    }

    /**
     * Returns the names of any target ("Move Users To") groups in the submitted
     * mapping that the current user isn't allowed to assign users to. Admins pass
     * everything; everyone else is limited to groups they hold the per-group
     * "assignUserGroup:<uid>" permission for.
     *
     * @param array $userGroups The submitted source => config mapping.
     * @return string[]
     */
    private function unauthorizedTargetGroups(array $userGroups): array
    {
        $currentUser = Craft::$app->getUser()->getIdentity();
        $userGroupsService = Craft::$app->getUserGroups();
        $unauthorized = [];

        foreach ($userGroups as $config) {
            if (empty($config['enabled']) || empty($config['contentFreezeGroup'])) {
                continue;
            }

            $group = $userGroupsService->getGroupById((int) $config['contentFreezeGroup']);

            if ($group === null) {
                continue;
            }

            if ($currentUser === null || !$currentUser->can("assignUserGroup:$group->uid")) {
                $unauthorized[$group->id] = $group->name;
            }
        }

        return array_values($unauthorized);
    }

    /**
     * Returns the names of any target ("Move Users To") groups in the submitted
     * mapping that are fed from more than one *source* user group - across this
     * freeze and every other freeze.
     *
     * The same source mapped to the same target in multiple freezes is fine
     * (restore is unambiguous: everyone in the target came from that one source).
     * Two *different* sources sharing a target is the problem - lifting a freeze
     * moves the whole target group back to a single source, misrouting the rest.
     *
     * @return string[]
     */
    private function conflictingTargetGroups(Freeze $freeze): array
    {
        $userGroupsService = Craft::$app->getUserGroups();

        // target group id => set of distinct source group ids mapping into it.
        $sourcesByTarget = [];

        $collect = static function ($userGroups) use (&$sourcesByTarget): void {
            foreach ($userGroups as $sourceId => $config) {
                if (empty($config['enabled']) || empty($config['contentFreezeGroup'])) {
                    continue;
                }

                $sourcesByTarget[(int) $config['contentFreezeGroup']][(int) $sourceId] = true;
            }
        };

        // This freeze's submitted mapping, plus every other freeze's.
        $collect($freeze->userGroups);

        foreach (Plugin::getInstance()->freezes->getAllFreezes() as $other) {
            if ($other->id !== $freeze->id) {
                $collect($other->userGroups);
            }
        }

        $conflicts = [];

        foreach ($freeze->userGroups as $config) {
            if (empty($config['enabled']) || empty($config['contentFreezeGroup'])) {
                continue;
            }

            $targetId = (int) $config['contentFreezeGroup'];

            if (count($sourcesByTarget[$targetId] ?? []) > 1) {
                $group = $userGroupsService->getGroupById($targetId);

                if ($group !== null) {
                    $conflicts[$group->id] = $group->name;
                }
            }
        }

        return array_values($conflicts);
    }
}
