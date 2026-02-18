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

            $currentGroupIds = array_map(fn($g) => $g->id, $user->getGroups());

            if (!in_array((int) $groupToId, $currentGroupIds)) {

                // Remove from source group, add to target group, preserve others
                $newGroupIds = array_filter($currentGroupIds, fn($id) => $id != $groupFromId);
                $newGroupIds[] = (int) $groupToId;
                $newGroupIds = array_values(array_unique($newGroupIds));
                Craft::$app->getUsers()->assignUserToGroups($user->id, $newGroupIds);

            }
        }
    }

}
