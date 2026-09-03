<?php

namespace bymayo\craftcontentfreeze\controllers;

use bymayo\craftcontentfreeze\Plugin;

use Craft;
use craft\web\Controller;
use craft\models\UserGroup;
use yii\web\Response;

class UserGroupsController extends Controller
{
    protected array|bool|int $allowAnonymous = false;

    public function actionCloneAndRedirect(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('manageUserGroups');

        $groupId = (int) Craft::$app->request->getRequiredBodyParam('groupId');
        $originalGroup = Craft::$app->userGroups->getGroupById($groupId);

        if (!$originalGroup) {
            Craft::$app->getSession()->setError(Craft::t('content-freeze', 'Original group not found.'));
            return $this->redirect(Craft::$app->request->getReferrer() ?: 'content-freeze');
        }

        // Use the name entered in the prompt, falling back to the default.
        $newGroupName = trim((string) Craft::$app->request->getBodyParam('name', ''));

        if ($newGroupName === '') {
            $newGroupName = $originalGroup->name . ' (Content Freeze)';
        }

        $newGroup = new UserGroup();
        $newGroup->name = $newGroupName;
        $newGroup->handle = $this->generateHandle($newGroupName);

        if (!Craft::$app->userGroups->saveGroup($newGroup)) {
            Craft::$app->getSession()->setError(Craft::t('content-freeze', 'Couldn’t create the group: {errors}', [
                'errors' => implode(', ', $newGroup->getFirstErrors()),
            ]));
            return $this->redirect(Craft::$app->request->getReferrer() ?: 'content-freeze');
        }

        // Set view-only permissions based on original group
        $this->setViewOnlyPermissions($newGroup, $originalGroup);

        Craft::$app->getSession()->setNotice(Craft::t('content-freeze', 'Group cloned.'));
        return $this->redirect(Craft::$app->request->getReferrer() ?: 'content-freeze');
    }

    /**
     * Permissions kept on the cloned "view only" group in addition to any that
     * start with "view" (Craft's core view-entry/category/etc. permissions).
     *
     * These let frozen users keep *viewing* supported plugins' content without
     * being able to edit it. Names are lowercase because Craft stores and returns
     * permission names lowercased.
     */
    private const VIEW_ONLY_KEEP_PERMISSIONS = [
        // Craft Commerce
        'accessplugin-commerce',
        'commerce-manageorders',
        // Solspace Freeform
        'accessplugin-freeform',
        'freeform-formsaccess',
        'freeform-submissionsaccess',
        'freeform-submissionsread',
        'freeform-notificationsaccess',
        // Verbb Formie
        'accessplugin-formie',
        'formie-accessforms',
        'formie-accesssubmissions',
        'formie-accesssentnotifications',
        // nystudio107 SEOmatic (dashboard is read-only; the editable meta sections
        // are dropped so SEO can't be changed during a freeze).
        'accessplugin-seomatic',
        'seomatic:dashboard',
        // Verbb Comments (keep access to the comments index; the edit/trash/delete
        // moderation permissions are dropped).
        'accessplugin-comments',
        // Verbb Navigation (section access only — Navigation has no view-only
        // permission, so individual navs stay hidden while frozen).
        'accessplugin-navigation',
    ];

    private function setViewOnlyPermissions(UserGroup $newGroup, UserGroup $originalGroup): void
    {
        $viewOnlyPermissions = [];

        // Built-in keep-list plus any extra permissions configured for other plugins.
        $extra = array_map('strtolower', Plugin::getInstance()->getSettings()->viewOnlyKeepPermissions);
        $keep = array_merge(self::VIEW_ONLY_KEEP_PERMISSIONS, $extra);

        $originalPermissions = Craft::$app->getUserPermissions()->getPermissionsByGroupId($originalGroup->id);

        foreach ($originalPermissions as $permission) {
            $normalized = strtolower($permission);

            // Keep core "view*" permissions and the supported plugins' view/read/access ones.
            if (str_starts_with($normalized, 'view') || in_array($normalized, $keep, true)) {
                $viewOnlyPermissions[] = $permission;
            }
        }

        // The clone exists to give frozen users read-only CP access, so it always
        // needs accessCp. Everything else is only carried over if the original
        // group had it - the clone should never grant more than it started with.
        $viewOnlyPermissions[] = 'accessCp';

        if (in_array('accesssitewhensystemisoff', array_map('strtolower', $originalPermissions), true)) {
            $viewOnlyPermissions[] = 'accessSiteWhenSystemIsOff';
        }

        Craft::$app->getUserPermissions()->saveGroupPermissions($newGroup->id, $viewOnlyPermissions);
    }

    /**
     * Generate a unique handle for the new group
     */
    private function generateHandle(string $name): string
    {
        $handle = strtolower(trim($name));
        $handle = preg_replace('/[^a-z0-9\s]/', '', $handle);
        $handle = preg_replace('/\s+/', ' ', $handle);
        $handle = trim($handle);

        $words = explode(' ', $handle);
        $handle = $words[0];
        for ($i = 1; $i < count($words); $i++) {
            $handle .= ucfirst($words[$i]);
        }

        if (!preg_match('/^[a-z]/', $handle)) {
            $handle = 'group' . ucfirst($handle);
        }

        $originalHandle = $handle;
        $counter = 1;

        while (Craft::$app->userGroups->getGroupByHandle($handle)) {
            $handle = $originalHandle . $counter;
            $counter++;
        }

        return $handle;
    }
}
