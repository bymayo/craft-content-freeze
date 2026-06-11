<?php

namespace bymayo\craftcontentfreeze\widgets;

use bymayo\craftcontentfreeze\models\Freeze;
use bymayo\craftcontentfreeze\Plugin;

use Craft;
use craft\base\Widget;

/**
 * Dashboard widget listing any active freezes and upcoming (scheduled) ones.
 */
class ContentFreeze extends Widget
{
    public static function displayName(): string
    {
        return Craft::t('content-freeze', 'Content Freeze');
    }

    public static function isSelectable(): bool
    {
        // Only offer the widget to users who can access the plugin section.
        return parent::isSelectable()
            && Craft::$app->getUser()->checkPermission('accessPlugin-content-freeze');
    }

    protected static function allowMultipleInstances(): bool
    {
        return false;
    }

    public static function icon(): ?string
    {
        return 'snowflake';
    }

    public function getTitle(): ?string
    {
        return Craft::t('content-freeze', 'Content Freeze');
    }

    public function getBodyHtml(): ?string
    {
        // Re-check on render - permissions may have changed since the widget was added.
        if (!Craft::$app->getUser()->checkPermission('accessPlugin-content-freeze')) {
            return null;
        }

        $active = [];
        $upcoming = [];

        foreach (Plugin::getInstance()->freezes->getAllFreezes() as $freeze) {
            $status = $freeze->getStatus();

            if ($status === Freeze::STATUS_ACTIVE) {
                $active[] = $freeze;
            } elseif ($status === Freeze::STATUS_SCHEDULED) {
                $upcoming[] = $freeze;
            }
        }

        // Order each group by start date (open-ended first), matching the freeze
        // index. Active freezes render before upcoming ones in the template.
        $byDateFrom = fn(Freeze $a, Freeze $b) =>
            ($a->dateFrom?->getTimestamp() ?? PHP_INT_MIN) <=> ($b->dateFrom?->getTimestamp() ?? PHP_INT_MIN);

        usort($active, $byDateFrom);
        usort($upcoming, $byDateFrom);

        return Craft::$app->getView()->renderTemplate('content-freeze/_widget/body', [
            'active' => $active,
            'upcoming' => $upcoming,
        ]);
    }
}
