<?php

namespace bymayo\craftcontentfreeze\services;

use bymayo\craftcontentfreeze\jobs\BackupJob;
use bymayo\craftcontentfreeze\jobs\MoveUsersJob;
use bymayo\craftcontentfreeze\jobs\NotifyJob;
use bymayo\craftcontentfreeze\models\Freeze;
use bymayo\craftcontentfreeze\Plugin;
use bymayo\craftcontentfreeze\records\Freeze as FreezeRecord;

use Craft;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\Json;
use DateTime;
use DateTimeInterface;
use yii\base\Component;

/**
 * Freezes service.
 *
 * Owns the freeze records and the logic that reconciles user group membership
 * with whichever freezes are currently in effect. "Reconcile" is time-aware: it
 * derives the desired group mapping from the current time and every enabled
 * freeze, so a window opening or closing is detected and applied automatically.
 */
class Freezes extends Component
{
    private const APPLIED_STATE_CACHE_KEY = 'content-freeze.appliedState';
    private const ACTIVE_IDS_CACHE_KEY = 'content-freeze.activeFreezeIds';

    /**
     * @var Freeze[]|null Per-request cache of the hydrated freeze list. A single
     * CP request reconciles, resolves the effective notice and (on login) checks
     * again - without this each of those re-queries and re-hydrates every freeze.
     */
    private ?array $allFreezes = null;

    // CRUD
    // =========================================================================

    /**
     * @return Freeze[]
     */
    public function getAllFreezes(): array
    {
        if ($this->allFreezes !== null) {
            return $this->allFreezes;
        }

        $records = FreezeRecord::find()
            ->orderBy(['dateCreated' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        return $this->allFreezes = array_map(
            fn(FreezeRecord $record) => $this->createFreezeFromRecord($record),
            $records
        );
    }

    public function getFreezeById(int $id): ?Freeze
    {
        $record = FreezeRecord::findOne($id);

        return $record ? $this->createFreezeFromRecord($record) : null;
    }

    public function saveFreeze(Freeze $freeze, bool $runValidation = true): bool
    {
        if ($runValidation && !$freeze->validate()) {
            return false;
        }

        $record = $freeze->id ? FreezeRecord::findOne($freeze->id) : new FreezeRecord();

        if ($record === null) {
            return false;
        }

        $record->name = $freeze->name;
        $record->description = $freeze->description;
        $record->enabled = $freeze->enabled;
        $record->notifyUsers = $freeze->notifyUsers;
        $record->dateFrom = $freeze->dateFrom ? Db::prepareDateForDb($freeze->dateFrom) : null;
        $record->dateTo = $freeze->dateTo ? Db::prepareDateForDb($freeze->dateTo) : null;
        $record->showNoticePane = $freeze->showNoticePane;
        $record->noticePaneHeading = $freeze->noticePaneHeading;
        $record->noticePaneText = $freeze->noticePaneText;
        $record->showNoticeBar = $freeze->showNoticeBar;
        $record->noticeBarText = $freeze->noticeBarText;
        $record->userGroups = Json::encode($this->normalizeMapping($freeze->userGroups));

        if (!$record->save(false)) {
            return false;
        }

        $freeze->id = (int) $record->id;
        $freeze->uid = $record->uid;

        $this->allFreezes = null;

        return true;
    }

    public function deleteFreezeById(int $id): bool
    {
        $record = FreezeRecord::findOne($id);

        if ($record === null) {
            return false;
        }

        $deleted = (bool) $record->delete();

        if ($deleted) {
            $this->allFreezes = null;
        }

        return $deleted;
    }

    // State
    // =========================================================================

    /**
     * @return Freeze[]
     */
    public function getActiveFreezes(?DateTimeInterface $now = null): array
    {
        return array_values(array_filter(
            $this->getAllFreezes(),
            fn(Freeze $freeze) => $freeze->isInEffect($now)
        ));
    }

    /**
     * The date envelope across all active freezes, for display in the notice.
     *
     * @return array{from: ?DateTime, to: ?DateTime}
     */
    public function getActiveDateRange(?DateTimeInterface $now = null): array
    {
        $from = null;
        $to = null;

        foreach ($this->getActiveFreezes($now) as $freeze) {
            if ($freeze->dateFrom !== null && ($from === null || $freeze->dateFrom < $from)) {
                $from = $freeze->dateFrom;
            }
            if ($freeze->dateTo !== null && ($to === null || $freeze->dateTo > $to)) {
                $to = $freeze->dateTo;
            }
        }

        return ['from' => $from, 'to' => $to];
    }

    /**
     * Resolves the notice (pane/bar) settings to display while a freeze is in
     * effect. The earliest active freeze wins. Its show toggles always apply;
     * any blank text field falls back to the plugin-level default. The dates
     * returned are that freeze's own window, for use in the notice templates.
     *
     * Returns null when no freeze is currently in effect.
     *
     * @return array{
     *     showNoticePane: bool,
     *     noticePaneHeading: string,
     *     noticePaneText: string,
     *     showNoticeBar: bool,
     *     noticeBarText: string,
     *     dateFrom: ?DateTime,
     *     dateTo: ?DateTime
     * }|null
     */
    public function getEffectiveNotice(?DateTimeInterface $now = null): ?array
    {
        $active = $this->getActiveFreezes($now);

        if (empty($active)) {
            return null;
        }

        // getAllFreezes() is ordered by creation, so index 0 is the earliest.
        $freeze = $active[0];
        $settings = Plugin::getInstance()->getSettings();

        return [
            'showNoticePane' => $freeze->showNoticePane,
            'noticePaneHeading' => $freeze->noticePaneHeading !== '' ? $freeze->noticePaneHeading : $settings->noticePaneHeading,
            'noticePaneText' => $freeze->noticePaneText !== '' ? $freeze->noticePaneText : $settings->noticePaneText,
            'showNoticeBar' => $freeze->showNoticeBar,
            'noticeBarText' => $freeze->noticeBarText !== '' ? $freeze->noticeBarText : $settings->noticeBarText,
            'dateFrom' => $freeze->dateFrom,
            'dateTo' => $freeze->dateTo,
        ];
    }

    /**
     * Replaces the notice tokens in a pane/bar message with the freeze's own
     * window: {dateFrom}, {dateTo} and {remaining} (e.g. "3 hours"). Open-ended
     * freezes fall back to wording that still reads as a sentence.
     *
     * @param array{
     *     showNoticePane: bool,
     *     noticePaneHeading: string,
     *     noticePaneText: string,
     *     showNoticeBar: bool,
     *     noticeBarText: string,
     *     dateFrom: ?DateTime,
     *     dateTo: ?DateTime
     * } $notice A notice from getEffectiveNotice()
     */
    public function formatNoticeText(string $text, array $notice, ?DateTimeInterface $now = null): string
    {
        $formatter = Craft::$app->getFormatter();

        return strtr($text, [
            '{dateFrom}' => $notice['dateFrom'] !== null
                ? $formatter->asDatetime($notice['dateFrom'], 'short')
                : Craft::t('content-freeze', 'now'),
            '{dateTo}' => $notice['dateTo'] !== null
                ? $formatter->asDatetime($notice['dateTo'], 'short')
                : Craft::t('content-freeze', 'further notice'),
            '{remaining}' => $this->getRemainingDuration($notice['dateTo'], $now),
        ]);
    }

    /**
     * How long is left of a freeze, in round terms (e.g. "3 hours"). Only the
     * largest unit is used, so the notice stays readable - an exact countdown
     * would be out of date the moment the page renders anyway. Open-ended
     * freezes have no end to count down to.
     */
    private function getRemainingDuration(?DateTimeInterface $dateTo, ?DateTimeInterface $now = null): string
    {
        if ($dateTo === null) {
            return Craft::t('content-freeze', 'a while');
        }

        $now ??= DateTimeHelper::now();
        $seconds = $dateTo->getTimestamp() - $now->getTimestamp();

        if ($seconds < 60) {
            return Craft::t('content-freeze', 'a few moments');
        }

        // Craft's own translated plural messages, so this follows the CP
        // language. Each unit is capped just below the next one up, so 59.9
        // minutes rounds to "59 minutes" rather than "60 minutes".
        $units = [
            [604800, null, '{num, number} {num, plural, =1{week} other{weeks}}'],
            [86400, 6, '{num, number} {num, plural, =1{day} other{days}}'],
            [3600, 23, '{num, number} {num, plural, =1{hour} other{hours}}'],
            [60, 59, '{num, number} {num, plural, =1{minute} other{minutes}}'],
        ];

        foreach ($units as [$unitSeconds, $max, $message]) {
            if ($seconds >= $unitSeconds) {
                $num = (int) round($seconds / $unitSeconds);

                return Craft::t('app', $message, [
                    'num' => $max !== null ? min($num, $max) : $num,
                ]);
            }
        }

        return Craft::t('content-freeze', 'a few moments');
    }

    /**
     * The desired "source group id => target group id" mapping for the freezes
     * currently in effect. When two active freezes map the same source group to
     * different targets, the earlier freeze wins (deterministic ordering).
     *
     * @return array<int, int>
     */
    public function computeDesiredMappings(?DateTimeInterface $now = null): array
    {
        $desired = [];

        foreach ($this->getActiveFreezes($now) as $freeze) {
            foreach ($this->normalizeMapping($freeze->userGroups) as $sourceId => $config) {
                if ($config['enabled'] && $config['contentFreezeGroup'] !== null) {
                    $desired[$sourceId] ??= $config['contentFreezeGroup'];
                }
            }
        }

        ksort($desired);

        return $desired;
    }

    /**
     * Every source group with a target configured across all freezes, mapped to
     * its freeze (target) group. Used to restore users when a freeze stops
     * applying - so it deliberately ignores the per-row "enabled" flag and the
     * freeze's own enabled state: a row that's been switched off still needs its
     * users moved back, which only happens if it's part of the universe.
     *
     * @return array<int, int>
     */
    public function getSourceGroupUniverse(): array
    {
        $universe = [];

        foreach ($this->getAllFreezes() as $freeze) {
            $universe += $this->getFreezeMapping($freeze);
        }

        return $universe;
    }

    /**
     * The source group id => target group id mapping for a single freeze, for
     * every row that points at a target group (regardless of the row's enabled
     * flag). Used to restore a freeze's users when the freeze itself is deleted
     * and so no longer appears in the universe.
     *
     * @return array<int, int>
     */
    public function getFreezeMapping(Freeze $freeze): array
    {
        $mapping = [];

        foreach ($this->normalizeMapping($freeze->userGroups) as $sourceId => $config) {
            if ($config['contentFreezeGroup'] !== null) {
                $mapping[$sourceId] = $config['contentFreezeGroup'];
            }
        }

        return $mapping;
    }

    /**
     * Moves users to match the given desired state.
     *
     * @param array<int, int> $desired   source group id => target group id (in effect now)
     * @param array<int, int> $universe  source group id => target group id (all freezes)
     */
    public function apply(array $desired, array $universe): void
    {
        $userGroups = Plugin::getInstance()->userGroups;

        foreach ($universe as $sourceId => $freezeGroupId) {
            if (isset($desired[$sourceId])) {
                // Freeze: move the source group's members into the freeze group.
                $userGroups->assignGroups($sourceId, $desired[$sourceId]);
            } else {
                // Restore: move the freeze group's members back to the source.
                $userGroups->assignGroups($freezeGroupId, $sourceId);
            }
        }
    }

    /**
     * Queues a user move whenever the effective freeze state has changed.
     *
     * The signature is derived from the current time and all enabled freezes, so
     * crossing a window boundary changes it and triggers a move. The move job is
     * idempotent, so re-running it (e.g. after the cache is cleared) is harmless.
     *
     * $extraUniverse adds source => target rows to the restore universe for
     * freezes that no longer exist (e.g. one just deleted), so their users are
     * still moved back. It's baked into the job payload, so a retry keeps it too.
     *
     * @param array<int, int> $extraUniverse source group id => target group id
     */
    public function reconcile(bool $force = false, array $extraUniverse = []): void
    {
        // Detect scheduled/active/ended transitions (backups + email notifications).
        // Runs before the move so a backup reflects the pre-freeze state.
        $this->handleFreezeTransitions();

        $cache = Craft::$app->getCache();
        $desired = $this->computeDesiredMappings();
        $signature = md5(Json::encode($desired));

        if (!$force && $extraUniverse === [] && $cache->get(self::APPLIED_STATE_CACHE_KEY) === $signature) {
            return;
        }

        // Set the marker before pushing so a concurrent request short-circuits.
        $cache->set(self::APPLIED_STATE_CACHE_KEY, $signature);

        $job = new MoveUsersJob();
        $job->desired = $desired;
        // Remaining freezes take precedence on key collision; the extras only
        // fill in sources that are no longer covered by any existing freeze.
        $job->universe = $this->getSourceGroupUniverse() + $extraUniverse;

        Craft::$app->getQueue()->push($job);
    }

    /**
     * Clears the "last applied" marker so the next reconcile() re-queues the move.
     * Called when the move job fails - otherwise the matching signature would make
     * reconcile() short-circuit forever and the failed move would never retry.
     */
    public function clearAppliedState(): void
    {
        Craft::$app->getCache()->delete(self::APPLIED_STATE_CACHE_KEY);
    }

    /**
     * Detects freezes crossing a status boundary (scheduled / active / ended) and
     * reacts: a database backup when one becomes active (if enabled), and email
     * notifications for any freeze with "notify users" turned on.
     *
     * The previous scheduled/active id sets are stored in the cache and compared
     * each reconcile, so each transition fires once - not on every request, and
     * not retroactively for freezes already in that state when the baseline (or
     * the cache) was first seen.
     */
    private function handleFreezeTransitions(): void
    {
        $cache = Craft::$app->getCache();

        $previous = $cache->get(self::ACTIVE_IDS_CACHE_KEY);
        $previous = is_array($previous) && isset($previous['active'], $previous['scheduled']) ? $previous : null;

        $currentActive = [];
        $currentScheduled = [];
        $byId = [];

        foreach ($this->getAllFreezes() as $freeze) {
            $byId[$freeze->id] = $freeze;
            $status = $freeze->getStatus();

            if ($status === Freeze::STATUS_ACTIVE) {
                $currentActive[] = $freeze->id;
            } elseif ($status === Freeze::STATUS_SCHEDULED) {
                $currentScheduled[] = $freeze->id;
            }
        }

        sort($currentActive);
        sort($currentScheduled);

        $cache->set(self::ACTIVE_IDS_CACHE_KEY, ['active' => $currentActive, 'scheduled' => $currentScheduled]);

        // No baseline yet (first run or evicted cache): don't fire retroactively.
        if ($previous === null) {
            return;
        }

        $newlyScheduled = array_diff($currentScheduled, $previous['scheduled']);
        $newlyActive = array_diff($currentActive, $previous['active']);
        $newlyEnded = array_diff($previous['active'], $currentActive);

        $queue = Craft::$app->getQueue();

        // Back up on any freeze newly becoming active.
        if ($newlyActive !== [] && Plugin::getInstance()->getSettings()->backupOnFreeze) {
            $queue->push(new BackupJob());
        }

        // Queue an email per transition, for freezes that still exist and opted in.
        $transitions = [
            'scheduled' => $newlyScheduled,
            'active' => $newlyActive,
            'ended' => $newlyEnded,
        ];

        foreach ($transitions as $event => $ids) {
            foreach ($ids as $id) {
                if (isset($byId[$id]) && $byId[$id]->notifyUsers) {
                    $queue->push(new NotifyJob(['freezeId' => $id, 'event' => $event]));
                }
            }
        }
    }

    // Helpers
    // =========================================================================

    private function createFreezeFromRecord(FreezeRecord $record): Freeze
    {
        $freeze = new Freeze();
        $freeze->id = (int) $record->id;
        $freeze->name = (string) $record->name;
        $freeze->description = (string) $record->description;
        $freeze->enabled = (bool) $record->enabled;
        $freeze->notifyUsers = (bool) $record->notifyUsers;
        $freeze->dateFrom = $record->dateFrom ? DateTimeHelper::toDateTime($record->dateFrom) : null;
        $freeze->dateTo = $record->dateTo ? DateTimeHelper::toDateTime($record->dateTo) : null;
        $freeze->showNoticePane = (bool) $record->showNoticePane;
        $freeze->noticePaneHeading = (string) $record->noticePaneHeading;
        $freeze->noticePaneText = (string) $record->noticePaneText;
        $freeze->showNoticeBar = (bool) $record->showNoticeBar;
        $freeze->noticeBarText = (string) $record->noticeBarText;
        $freeze->userGroups = $this->normalizeMapping(Json::decode($record->userGroups) ?: []);
        $freeze->dateCreated = $record->dateCreated ? DateTimeHelper::toDateTime($record->dateCreated) : null;
        $freeze->uid = $record->uid;

        return $freeze;
    }

    /**
     * Normalises a user groups mapping: integer keys, bool enabled, int|null
     * target, and only rows that actually point at a freeze group.
     *
     * @return array<int, array{enabled: bool, contentFreezeGroup: int|null}>
     */
    private function normalizeMapping(array $mapping): array
    {
        $normalized = [];

        foreach ($mapping as $sourceId => $config) {
            $target = $config['contentFreezeGroup'] ?? null;

            if (empty($target)) {
                continue;
            }

            $normalized[(int) $sourceId] = [
                'enabled' => !empty($config['enabled']),
                'contentFreezeGroup' => (int) $target,
            ];
        }

        return $normalized;
    }
}
