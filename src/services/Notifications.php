<?php

namespace bymayo\craftcontentfreeze\services;

use bymayo\craftcontentfreeze\models\Freeze;
use bymayo\craftcontentfreeze\Plugin;

use Craft;
use craft\elements\User;
use craft\i18n\Formatter;
use yii\base\Component;

/**
 * Sends the per-freeze email notifications, using editable System Messages
 * (Utilities -> System Messages) for the subject/body.
 */
class Notifications extends Component
{
    /**
     * @var array<string, Formatter> Per-language formatters, built on demand.
     */
    private array $formatters = [];

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

        $mailer = Craft::$app->getMailer();

        foreach ($recipients as $user) {
            try {
                // Craft renders the message in each recipient's preferred
                // language, so format their dates to match rather than baking in
                // whatever locale the queue worker happens to be running as.
                $formatter = $this->formatterForUser($user);

                $variables = [
                    'name' => $freeze->name,
                    'description' => $freeze->description,
                    'dateFrom' => $freeze->dateFrom ? $formatter->asDatetime($freeze->dateFrom, 'short') : null,
                    'dateTo' => $freeze->dateTo ? $formatter->asDatetime($freeze->dateTo, 'short') : null,
                ];

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
     * Both sides of the move count. The "ended" notification is queued before the
     * job that moves people back, so by the time it sends they're still in the
     * view-only group - looking only at the source groups would email nobody.
     *
     * @return User[]
     */
    private function recipients(Freeze $freeze): array
    {
        $groupIds = [];

        foreach ($freeze->userGroups as $sourceId => $config) {
            if (!empty($config['enabled']) && !empty($config['contentFreezeGroup'])) {
                $groupIds[] = (int) $sourceId;
                $groupIds[] = (int) $config['contentFreezeGroup'];
            }
        }

        if (empty($groupIds)) {
            return [];
        }

        $users = User::find()
            ->groupId(array_values(array_unique($groupIds)))
            ->all();

        return array_values(array_filter(
            $users,
            fn(User $user) => $user->can('accessCp')
        ));
    }

    /**
     * A formatter for the recipient's Formatting Locale preference (falling back
     * to their language, then to the app's own formatter). Both preferences are
     * validated by Craft, so anything non-null here is a known locale.
     */
    private function formatterForUser(User $user): Formatter
    {
        $locale = $user->getPreferredLocale() ?? $user->getPreferredLanguage();

        if ($locale === null) {
            return Craft::$app->getFormatter();
        }

        return $this->formatters[$locale] ??= Craft::$app->getI18n()->getLocaleById($locale)->getFormatter();
    }
}
