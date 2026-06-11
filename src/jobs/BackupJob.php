<?php

namespace bymayo\craftcontentfreeze\jobs;

use Craft;
use craft\queue\BaseJob;

/**
 * Creates a database backup, queued when a freeze becomes active and the
 * "Back Up Database on Freeze" setting is enabled.
 *
 * Runs in the queue because a backup can be slow and shouldn't block the request
 * that triggered the freeze. Backups are written to storage/backups.
 */
class BackupJob extends BaseJob
{
    public function execute($queue): void
    {
        Craft::$app->getDb()->backup();
    }

    protected function defaultDescription(): ?string
    {
        return Craft::t('content-freeze', 'Backing up the database (content freeze)');
    }
}
