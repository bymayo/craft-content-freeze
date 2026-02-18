<?php

namespace bymayo\craftcontentfreeze;

use Craft;
use bymayo\craftcontentfreeze\models\Settings;
use bymayo\craftcontentfreeze\services\UserGroups;
use craft\base\Model;
use yii\base\Event;
use craft\base\Plugin as BasePlugin;
use yii\web\User;
use craft\helpers\UrlHelper;
use craft\web\UrlManager;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterCpAlertsEvent;
use craft\helpers\Cp as CpHelper;

/**
 * Content Freeze plugin
 *
 * @method static Plugin getInstance()
 * @method Settings getSettings()
 * @author Jason Mayo <jason@bymayo.co.uk>
 * @copyright Jason Mayo
 * @license MIT
 * @property-read UserGroups $userGroups
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => ['userGroups' => UserGroups::class],
        ];
    }

    public function init(): void
    {
        parent::init();

        $this->attachEventHandlers();
    }

    /**
     * Register the plugin's controllers
     */
    public function getCpNavItems(): array
    {
        $items = parent::getCpNavItems();

        // Add controller routes
        $items[] = [
            'url' => 'content-freeze/user-groups',
            'label' => 'User Groups',
            'icon' => '@bymayo/craftcontentfreeze/icon.svg',
        ];

        return $items;
    }

    /**
     * Register the plugin's controller routes
     */
    public function getControllerNamespace(): string
    {
        return 'bymayo\craftcontentfreeze\controllers';
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('content-freeze/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
            'config' => Craft::$app->getConfig()->getConfigFromFile('content-freeze')
        ]);
    }

    private function attachEventHandlers(): void
    {

        Event::on(
            Plugin::class,
            Plugin::EVENT_AFTER_SAVE_SETTINGS,
            function (Event $event) {

                $plugin = $event->sender;
                $this->handleSettingsSave($plugin->getSettings());

            }
        );

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['content-freeze'] = 'content-freeze/pane/content-freeze';
            }
        );

        Event::on(
            CpHelper::class,
            CpHelper::EVENT_REGISTER_ALERTS,
            function (RegisterCpAlertsEvent $event) {

                $settings = $this->getSettings();

                if ($settings->enabled && $settings->showNoticeBar) {

                    $event->alerts = array_merge($event->alerts, [
                        [
                            'content' => $settings->noticeBarText,
                            'showIcon' => true,
                        ],
                    ]);

                }
            }
        );

        Event::on(
            User::class,
            User::EVENT_AFTER_LOGIN,
            function() {

                $user = Craft::$app->getUser();
                $request = Craft::$app->getRequest();

                if ($request->getIsCpRequest()) {

                    $settings = $this->getSettings();

                    if ($settings->enabled && $settings->showNoticePane) {
                        $user->setReturnUrl(UrlHelper::cpUrl('content-freeze'));
                    }

                }
            }
        );

    }

    /**
     * Handle plugin settings save
     */
    private function handleSettingsSave($settings): void
    {

        $this->userGroups->moveUsers();

    }
}
