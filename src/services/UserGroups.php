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

    public function moveUsers($groupFromId, $groupToId)
    {

        Plugin::log("Moving users from " . $groupFromId . " to " . $groupToId);

        $users = User::find()->groupId($groupFromId)->all();
        foreach ($users as $user) {

            Craft::$app->getUsers()->assignUserToGroups($user->id, [$groupToId]);

        }
    }

}
