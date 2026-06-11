<?php

namespace bymayo\craftcontentfreeze\jobs;

use bymayo\craftcontentfreeze\Plugin;

use Craft;
use craft\queue\BaseJob;

/**
 * Emails the affected users about a freeze transition (scheduled / active /
 * ended). Queued from the reconcile transition detection so the request that
 * triggered the change isn't blocked while emails are sent.
 */
class NotifyJob extends BaseJob
{
    public ?int $freezeId = null;

    /**
     * @var string One of "scheduled", "active", "ended".
     */
    public string $event = '';

    public function execute($queue): void
    {
        if ($this->freezeId === null) {
            return;
        }

        Plugin::getInstance()->notifications->send($this->freezeId, $this->event);
    }

    protected function defaultDescription(): ?string
    {
        return Craft::t('content-freeze', 'Sending content freeze notifications');
    }
}
