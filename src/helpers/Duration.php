<?php

namespace bymayo\craftcontentfreeze\helpers;

use Craft;

/**
 * Formats a duration in round terms, using only the largest unit - "3 hours",
 * not "3 hours, 27 minutes".
 *
 * Shared by everything that counts down to (or up from) a freeze boundary: the
 * freeze list, the dashboard widget and the `{remaining}` notice token. They all
 * describe the same window, so they must agree on the wording.
 */
abstract class Duration
{
    /**
     * Seconds per unit, the highest value each unit may show, and Craft's own
     * translated plural message for it.
     *
     * Each unit is capped just below the next one up so rounding can't spill
     * over: 59.9 minutes reads as "59 minutes", not "60 minutes".
     */
    private const UNITS = [
        [604800, null, '{num, number} {num, plural, =1{week} other{weeks}}'],
        [86400, 6, '{num, number} {num, plural, =1{day} other{days}}'],
        [3600, 23, '{num, number} {num, plural, =1{hour} other{hours}}'],
        [60, 59, '{num, number} {num, plural, =1{minute} other{minutes}}'],
    ];

    /**
     * Humanises a number of seconds, e.g. "2 weeks", "3 hours", "45 minutes".
     * Anything under a minute (or in the past) is "less than a minute".
     */
    public static function humanize(int $seconds): string
    {
        foreach (self::UNITS as [$unitSeconds, $max, $message]) {
            if ($seconds >= $unitSeconds) {
                $num = (int) round($seconds / $unitSeconds);

                // Craft's own app-category messages, so the unit follows the
                // CP language without the plugin shipping its own translations.
                return Craft::t('app', $message, [
                    'num' => $max !== null ? min($num, $max) : $num,
                ]);
            }
        }

        return Craft::t('content-freeze', 'less than a minute');
    }
}
