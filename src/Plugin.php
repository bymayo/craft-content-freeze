<?php

namespace bymayo\craftcontentfreeze;

use Craft;
use bymayo\craftcontentfreeze\models\Settings;
use bymayo\craftcontentfreeze\services\UserGroups;
use craft\base\Model;
use yii\base\Event;
use craft\events\PluginEvent;
use craft\base\Plugin as CraftPlugin;
use craft\base\Plugin as BasePlugin;
use craft\helpers\FileHelper;

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

    public static function log($message)
    {
        $file = Craft::getAlias('@storage/logs/content-freeze.log');
        $log = date('Y-m-d H:i:s'). ' ' . $message . "\n";
        FileHelper::writeToFile($file, $log, ['append' => true]);
    }

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

        // Any code that creates an element query or loads Twig should be deferred until
        // after Craft is fully initialized, to avoid conflicts with other plugins/modules
        Craft::$app->onInit(function() {
            // ...
        });
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
        ]);
    }

    private function attachEventHandlers(): void
    {

        Event::on(
            Plugin::class,
            Plugin::EVENT_AFTER_SAVE_SETTINGS,
            function (Event $event) {
                // Check if this event is for our plugin
                // if (in_array('content-freeze', $event->plugins)) {
                    $this->log("Content Freeze plugin settings saved");

                    $plugin = $event->sender;

                    $this->handleSettingsSave($plugin->getSettings());

                // }
            }
        );

    }

    /**
     * Handle plugin settings save
     */
    private function handleSettingsSave($settings): void
    {

        $memberGroups = Craft::$app->userGroups->getAllGroups();

        foreach ($memberGroups as $group) {

            $groupSettings = $settings['memberGroups'][$group->id] ?? null;

            if ($groupSettings !== null && $groupSettings['enable']) {

                if ($settings['enable']) {
                    $this->log("Moving users from " . $group->id . " to " . $groupSettings['contentFreezeGroup']);
                    $this->userGroups->moveUsers($group->id, $groupSettings['contentFreezeGroup']);
                }
                else {
                    $this->log("Moving users from " . $groupSettings['contentFreezeGroup'] . " to " . $group->id);
                    $this->userGroups->moveUsers($groupSettings['contentFreezeGroup'], $group->id);
                }

            }

        }
    }
}
