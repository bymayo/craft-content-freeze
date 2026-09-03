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

        // Only the ids are needed, so don't hydrate a full element per user -
        // a freeze can cover thousands of them.
        $userIds = array_map('intval', User::find()->groupId($groupFromId)->ids());

        if (empty($userIds)) {
            return;
        }

        // Fetch every affected user's current group ids in a single query rather
        // than calling $user->getGroups() (one query per user).
        $currentGroupIds = $this->currentGroupIdsByUser($userIds);

        $usersService = Craft::$app->getUsers();

        foreach ($userIds as $userId) {
            $groupIds = $currentGroupIds[$userId] ?? [];

            if (in_array($groupToId, $groupIds, true)) {
                continue;
            }

            // Remove from source group, add to target group, preserve others.
            $newGroupIds = array_filter($groupIds, fn(int $id) => $id !== $groupFromId);
            $newGroupIds[] = $groupToId;
            $newGroupIds = array_values(array_unique($newGroupIds));

            $usersService->assignUserToGroups($userId, $newGroupIds);
        }
    }

    /**
     * How many users are in each user group, keyed by group id, in one query -
     * the freeze edit screen shows a count per row, and querying per row would
     * be one COUNT per group.
     *
     * Matches what a `craft.users.groupId(x).count()` would return: enabled,
     * non-deleted user elements.
     *
     * @return array<int, int>
     */
    public function getUserCountsByGroup(): array
    {
        $rows = (new Query())
            ->select(['ugu.groupId', 'total' => 'COUNT(*)'])
            ->from(['ugu' => Table::USERGROUPS_USERS])
            ->innerJoin(['elements' => Table::ELEMENTS], '[[elements.id]] = [[ugu.userId]]')
            ->where([
                'elements.dateDeleted' => null,
                'elements.enabled' => true,
            ])
            ->groupBy(['ugu.groupId'])
            ->all();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(int) $row['groupId']] = (int) $row['total'];
        }

        return $counts;
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
