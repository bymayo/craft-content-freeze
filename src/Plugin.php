<?php

namespace bymayo\craftcontentfreeze;

use Craft;
use bymayo\craftcontentfreeze\models\Settings;
use bymayo\craftcontentfreeze\services\UserGroups;
use bymayo\craftcontentfreeze\services\Freezes;
use bymayo\craftcontentfreeze\services\Notifications;
use bymayo\craftcontentfreeze\widgets\ContentFreeze as ContentFreezeWidget;
use bymayo\craftcontentfreeze\variables\ContentFreezeVariable;
use craft\base\Model;
use yii\base\Event;
use craft\base\Plugin as BasePlugin;
use yii\web\User;
use craft\helpers\UrlHelper;
use craft\web\UrlManager;
use craft\web\twig\variables\CraftVariable;
use craft\services\Dashboard;
use craft\services\SystemMessages;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterCpAlertsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterEmailMessagesEvent;
use craft\helpers\Cp as CpHelper;
use craft\helpers\Html;

/**
 * Content Freeze plugin
 *
 * @method static Plugin getInstance()
 * @method Settings getSettings()
 * @author Jason Mayo <jason@bymayo.co.uk>
 * @copyright Jason Mayo
 * @license MIT
 * @property-read UserGroups $userGroups
 * @property-read Freezes $freezes
 * @property-read Notifications $notifications
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '2.0.0';
    public bool $hasCpSettings = true;
    public bool $hasCpSection = true;

    public static function config(): array
    {
        return [
            'components' => [
                'userGroups' => UserGroups::class,
                'freezes' => Freezes::class,
                'notifications' => Notifications::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        $this->attachEventHandlers();

        // Reconcile on control panel requests so freezes activate/deactivate at
        // their scheduled boundaries even between cron runs. Cheap: it only
        // queues a job when the effective state actually changes.
        $request = Craft::$app->getRequest();

        if (
            Craft::$app->getIsInstalled() &&
            !$request->getIsConsoleRequest() &&
            $request->getIsCpRequest()
        ) {
            $this->freezes->reconcile();
        }
    }

    /**
     * Register the plugin's CP nav item (the freezes index).
     *
     * Craft calls getCpNavItem() (singular) - not getCpNavItems() - so the icon
     * must be set here, otherwise Craft falls back to looking for icon-mask.svg
     * and renders a generic dot when it isn't found.
     */
    public function getCpNavItem(): ?array
    {
        $navItem = parent::getCpNavItem();
        $navItem['label'] = Craft::t('content-freeze', 'Content Freeze');
        $navItem['icon'] = '@bymayo/craftcontentfreeze/icon-outline.svg';

        return $navItem;
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('content-freeze/_settings/notices.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
            'config' => Craft::$app->getConfig()->getConfigFromFile('content-freeze'),
        ]);
    }

    private function attachEventHandlers(): void
    {

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['content-freeze'] = 'content-freeze/freezes/index';
                $event->rules['content-freeze/new'] = 'content-freeze/freezes/edit';
                $event->rules['content-freeze/<freezeId:\d+>'] = 'content-freeze/freezes/edit';
                $event->rules['content-freeze/notice'] = 'content-freeze/pane/content-freeze';
            }
        );

        Event::on(
            CpHelper::class,
            CpHelper::EVENT_REGISTER_ALERTS,
            function (RegisterCpAlertsEvent $event) {

                $notice = $this->freezes->getEffectiveNotice();

                if ($notice !== null && $notice['showNoticeBar']) {

                    $content = $this->freezes->formatNoticeText($notice['noticeBarText'], $notice);

                    // Craft renders CP alert content as raw HTML, so escape the
                    // user-supplied notice text to prevent stored XSS.
                    $event->alerts = array_merge($event->alerts, [
                        [
                            'content' => Html::encode($content),
                            'showIcon' => true,
                        ],
                    ]);

                }
            }
        );

        Event::on(
            Dashboard::class,
            Dashboard::EVENT_REGISTER_WIDGET_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = ContentFreezeWidget::class;
            }
        );

        // Expose the front-end `craft.contentFreeze` Twig variable.
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                $event->sender->set('contentFreeze', ContentFreezeVariable::class);
            }
        );

        // Register editable System Messages for the freeze notification emails.
        Event::on(
            SystemMessages::class,
            SystemMessages::EVENT_REGISTER_MESSAGES,
            function (RegisterEmailMessagesEvent $event) {
                $event->messages[] = [
                    'key' => 'content_freeze_scheduled',
                    'heading' => Craft::t('content-freeze', 'When a content freeze is scheduled:'),
                    'subject' => Craft::t('content-freeze', 'A content freeze has been scheduled: {{ name }}'),
                    'body' => Craft::t('content-freeze', "Hi {{ user.friendlyName }},\n\nA content freeze (**{{ name }}**) has been scheduled.\n\n{% if dateFrom %}- Starts: {{ dateFrom }}\n{% endif %}{% if dateTo %}- Ends: {{ dateTo }}\n{% endif %}\n\nWhile it is active, editing in the control panel will be paused.\n\n{% if description %}{{ description }}{% endif %}"),
                ];

                $event->messages[] = [
                    'key' => 'content_freeze_active',
                    'heading' => Craft::t('content-freeze', 'When a content freeze becomes active:'),
                    'subject' => Craft::t('content-freeze', 'A content freeze is now active: {{ name }}'),
                    'body' => Craft::t('content-freeze', "Hi {{ user.friendlyName }},\n\nA content freeze (**{{ name }}**) is now active, so editing in the control panel is paused.\n\n{% if dateTo %} Editing will resume on {{ dateTo }}.{% endif %}\n\n{% if description %}\n{{ description }}\n{% endif %}"),
                ];

                $event->messages[] = [
                    'key' => 'content_freeze_ended',
                    'heading' => Craft::t('content-freeze', 'When a content freeze ends:'),
                    'subject' => Craft::t('content-freeze', 'A content freeze has ended: {{ name }}'),
                    'body' => Craft::t('content-freeze', "Hi {{ user.friendlyName }},\n\nThe content freeze (**{{ name }}**) has ended. You can edit content again."),
                ];
            }
        );

        Event::on(
            User::class,
            User::EVENT_AFTER_LOGIN,
            function() {

                $user = Craft::$app->getUser();
                $request = Craft::$app->getRequest();

                if ($request->getIsCpRequest()) {

                    $notice = $this->freezes->getEffectiveNotice();

                    if ($notice !== null && $notice['showNoticePane']) {
                        $user->setReturnUrl(UrlHelper::cpUrl('content-freeze/notice'));
                    }

                }
            }
        );

    }
}
