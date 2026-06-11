<?php

namespace bymayo\craftcontentfreeze\jobs;

use bymayo\craftcontentfreeze\Plugin;

use Craft;
use craft\queue\BaseJob;

/**
 * Moves users between their normal and content-freeze user groups.
 *
 * Runs in the queue so the triggering request returns immediately, rather than
 * blocking while every affected user is reassigned. The desired/universe maps
 * are snapshotted at push time so the applied result matches the state that was
 * reconciled; if a window shifts before the job runs, the next reconcile heals it.
 */
class MoveUsersJob extends BaseJob
{
    /**
     * @var array<int, int> source group id => target group id (in effect now)
     */
    public array $desired = [];

    /**
     * @var array<int, int> source group id => target group id (all freezes)
     */
    public array $universe = [];

    public function execute($queue): void
    {
        $freezes = Plugin::getInstance()->freezes;

        try {
            $freezes->apply($this->desired, $this->universe);
        } catch (\Throwable $e) {
            // reconcile() set the "applied" marker before pushing this job. If we
            // fail, clear it so the next reconcile re-queues rather than assuming
            // the move already happened.
            $freezes->clearAppliedState();
            throw $e;
        }
    }

    protected function defaultDescription(): ?string
    {
        return Craft::t('app', 'Applying content freeze user group changes');
    }
}
