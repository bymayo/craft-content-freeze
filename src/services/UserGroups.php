<?php

namespace bymayo\craftcontentfreeze\services;

use Craft;
use craft\db\Query;
use craft\db\Table;
use craft\elements\User;
use yii\base\Component;

/**
 * User Groups service
 */
class UserGroups extends Component
{
    /**
     * Moves every member of the source group into the target group, preserving
     * their other group memberships.
     */
    public function assignGroups(int $groupFromId, int $groupToId): void
    {
        $userGroupsService = Craft::$app->getUserGroups();

        // Skip if either group no longer exists (e.g. a target group was deleted
        // after being mapped) - assigning to a missing group id would error.
        if (
            $userGroupsService->getGroupById($groupFromId) === null ||
            $userGroupsService->getGroupById($groupToId) === null
        ) {
            return;
        }

        $users = User::find()->groupId($groupFromId)->all();

        if (empty($users)) {
            return;
        }

        // Fetch every affected user's current group ids in a single query rather
        // than calling $user->getGroups() (one query per user).
        $userIds = array_map(fn(User $user) => (int) $user->id, $users);
        $currentGroupIds = $this->currentGroupIdsByUser($userIds);

        $usersService = Craft::$app->getUsers();

        foreach ($users as $user) {
            $groupIds = $currentGroupIds[(int) $user->id] ?? [];

            if (in_array($groupToId, $groupIds, true)) {
                continue;
            }

            // Remove from source group, add to target group, preserve others.
            $newGroupIds = array_filter($groupIds, fn(int $id) => $id !== $groupFromId);
            $newGroupIds[] = $groupToId;
            $newGroupIds = array_values(array_unique($newGroupIds));

            $usersService->assignUserToGroups($user->id, $newGroupIds);
        }
    }

    /**
     * Returns each user's current group ids, keyed by user id, in one query.
     *
     * @param int[] $userIds
     * @return array<int, int[]>
     */
    private function currentGroupIdsByUser(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $rows = (new Query())
            ->select(['userId', 'groupId'])
            ->from(Table::USERGROUPS_USERS)
            ->where(['userId' => $userIds])
            ->all();

        $map = [];

        foreach ($rows as $row) {
            $map[(int) $row['userId']][] = (int) $row['groupId'];
        }

        return $map;
    }
}
