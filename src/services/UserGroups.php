<?php

namespace bymayo\craftcontentfreeze\services;

use bymayo\craftcontentfreeze\Plugin;

use Craft;
use yii\base\Component;
use craft\elements\User;

/**
 * User Groups service
 */
class UserGroups extends Component
{

    public function moveUsers()
    {

        $settings = Plugin::getInstance()->getSettings();

        $userGroups = Craft::$app->userGroups->getAllGroups();

        // Member Groups 
        foreach ($userGroups as $group) {

            $groupSettings = $settings['userGroups'][$group->id] ?? null;

            if ($groupSettings !== null && $groupSettings['enabled']) {

                if ($settings['enabled']) {
                    $this->assignGroups($group->id, $groupSettings['contentFreezeGroup']);
                }
                else {
                    $this->assignGroups($groupSettings['contentFreezeGroup'], $group->id);
                }

            }

        }

    }

    public function assignGroups($groupFromId, $groupToId)
    {

        $users = User::find()->groupId($groupFromId)->all();
        
        foreach ($users as $user) {
     
            if (!$user->isInGroup($groupToId)) {

                Plugin::log("Moving users from " . $groupFromId . " to " . $groupToId);
                Craft::$app->getUsers()->assignUserToGroups($user->id, [$groupToId]);

            }
        }
    }

}
