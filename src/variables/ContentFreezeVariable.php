<?php

namespace bymayo\craftcontentfreeze\variables;

use bymayo\craftcontentfreeze\models\Freeze;
use bymayo\craftcontentfreeze\Plugin;

use DateTime;
use DateTimeInterface;

/**
 * Front-end Twig variable, available as `craft.contentFreeze`.
 *
 * Lets templates react to a freeze being in effect - e.g. hide a form, disable
 * add-to-cart, or show a message. It's purely time-based (a freeze is "in
 * effect" when enabled and within its window), so it's accurate on the front
 * end without relying on the control-panel reconcile.
 */
class ContentFreezeVariable
{
    /**
     * Whether any content freeze is currently in effect.
     *
     * `{% if craft.contentFreeze.enabled %}`
     */
    public function getEnabled(?DateTimeInterface $now = null): bool
    {
        return Plugin::getInstance()->freezes->getActiveFreezes($now) !== [];
    }

    /**
     * The freezes currently in effect.
     *
     * @return Freeze[]
     */
    public function getFreezes(?DateTimeInterface $now = null): array
    {
        return Plugin::getInstance()->freezes->getActiveFreezes($now);
    }

    /**
     * The combined start/end date range across all active freezes, for display.
     *
     * @return array{from: ?DateTime, to: ?DateTime}
     */
    public function getDateRange(?DateTimeInterface $now = null): array
    {
        return Plugin::getInstance()->freezes->getActiveDateRange($now);
    }
}
