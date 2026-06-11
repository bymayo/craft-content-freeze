<?php

namespace bymayo\craftcontentfreeze\services;

use bymayo\craftcontentfreeze\models\Freeze;
use bymayo\craftcontentfreeze\Plugin;

use Craft;
use craft\elements\User;
use yii\base\Component;

/**
 * Sends the per-freeze email notifications, using editable System Messages
 * (Utilities -> System Messages) for the subject/body.
 */
class Notifications extends Component
{
    public const MESSAGE_KEYS = [
        'scheduled' => 'content_freeze_scheduled',
        'active' => 'content_freeze_active',
        'ended' => 'content_freeze_ended',
    ];

    /**
     * Emails the affected users about a freeze transition.
     *
     * @param string $event One of "scheduled", "active", "ended".
     */
    public function send(int $freezeId, string $event): void
    {
        $key = self::MESSAGE_KEYS[$event] ?? null;

        if ($key === null) {
            return;
        }

        $freeze = Plugin::getInstance()->freezes->getFreezeById($freezeId);

        if ($freeze === null || !$freeze->notifyUsers) {
            return;
        }

        $recipients = $this->recipients($freeze);

        if (empty($recipients)) {
            return;
        }

        $formatter = Craft::$app->getFormatter();
        $variables = [
            'name' => $freeze->name,
            'description' => $freeze->description,
            'dateFrom' => $freeze->dateFrom ? $formatter->asDatetime($freeze->dateFrom, 'short') : null,
            'dateTo' => $freeze->dateTo ? $formatter->asDatetime($freeze->dateTo, 'short') : null,
        ];

        $mailer = Craft::$app->getMailer();

        foreach ($recipients as $user) {
            try {
                $mailer
                    ->composeFromKey($key, $variables + ['user' => $user])
                    ->setTo($user)
                    ->send();
            } catch (\Throwable $e) {
                Craft::error(
                    "Content Freeze: couldn’t email {$user->email}: {$e->getMessage()}",
                    __METHOD__
                );
            }
        }
    }

    /**
     * The users to notify: members of the freeze's enabled source groups who can
     * actually access the control panel (so front-end-only accounts are skipped).
     *
     * @return User[]
     */
    private function recipients(Freeze $freeze): array
    {
        $sourceIds = [];

        foreach ($freeze->userGroups as $sourceId => $config) {
            if (!empty($config['enabled']) && !empty($config['contentFreezeGroup'])) {
                $sourceIds[] = (int) $sourceId;
            }
        }

        if (empty($sourceIds)) {
            return [];
        }

        $users = User::find()
            ->groupId($sourceIds)
            ->all();

        return array_values(array_filter(
            $users,
            fn(User $user) => $user->can('accessCp')
        ));
    }
}
