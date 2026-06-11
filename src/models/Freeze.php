<?php

namespace bymayo\craftcontentfreeze\models;

use Craft;
use craft\base\Model;
use craft\helpers\DateTimeHelper;
use DateTime;
use DateTimeInterface;

/**
 * Freeze model
 *
 * Represents a single (optionally scheduled) content freeze. A freeze is "in
 * effect" when it is enabled and the current time falls within its window.
 * Either date may be omitted: no start means it applies as soon as it is
 * enabled, no end means it applies until it is disabled.
 */
class Freeze extends Model
{
    public const STATUS_DISABLED = 'disabled';
    public const STATUS_ENDED = 'ended';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_ACTIVE = 'active';

    public ?int $id = null;
    public string $name = '';
    public string $description = '';
    public bool $enabled = true;
    public bool $notifyUsers = false;
    public ?DateTime $dateFrom = null;
    public ?DateTime $dateTo = null;

    /**
     * Per-freeze notice settings. Blank text fields fall back to the
     * plugin-level defaults; the show toggles always apply as set.
     */
    public bool $showNoticePane = true;
    public string $noticePaneHeading = '';
    public string $noticePaneText = '';
    public bool $showNoticeBar = true;
    public string $noticeBarText = '';

    /**
     * Map of source user group id => ['enabled' => bool, 'contentFreezeGroup' => int|null].
     */
    public array $userGroups = [];

    public ?DateTime $dateCreated = null;
    public ?string $uid = null;

    public function defineRules(): array
    {
        return [
            [['name'], 'string'],
            [['name'], 'required'],
            [['description'], 'string'],
            [['enabled', 'notifyUsers', 'showNoticePane', 'showNoticeBar'], 'boolean'],
            [['noticePaneHeading', 'noticePaneText', 'noticeBarText'], 'string'],
            [['dateFrom', 'dateTo'], 'safe'],
            [['userGroups'], 'safe'],
            [['dateTo'], 'validateDateRange'],
            [['userGroups'], 'validateUserGroups'],
        ];
    }

    /**
     * Ensures the end date is not before the start date.
     */
    public function validateDateRange(string $attribute): void
    {
        if ($this->dateFrom !== null && $this->dateTo !== null && $this->dateTo < $this->dateFrom) {
            $this->addError($attribute, Craft::t('content-freeze', 'The end date can’t be before the start date.'));
        }
    }

    /**
     * Ensures at least one user group is enabled, and that every enabled row has
     * a "Move Users To" target chosen.
     */
    public function validateUserGroups(string $attribute): void
    {
        $enabledCount = 0;
        $missingTarget = false;

        foreach ($this->userGroups as $config) {
            if (empty($config['enabled'])) {
                continue;
            }

            $enabledCount++;

            if (empty($config['contentFreezeGroup'])) {
                $missingTarget = true;
            }
        }

        if ($enabledCount === 0) {
            $this->addError($attribute, Craft::t('content-freeze', 'Enable at least one user group and choose where to move its users.'));
        } elseif ($missingTarget) {
            $this->addError($attribute, Craft::t('content-freeze', 'Choose a “Move Users To” group for each enabled user group.'));
        }
    }

    /**
     * Whether the freeze is currently in effect.
     */
    public function isInEffect(?DateTimeInterface $now = null): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $now ??= DateTimeHelper::now();

        if ($this->dateFrom !== null && $now < $this->dateFrom) {
            return false;
        }

        if ($this->dateTo !== null && $now > $this->dateTo) {
            return false;
        }

        return true;
    }

    /**
     * Returns the freeze's current status.
     */
    public function getStatus(?DateTimeInterface $now = null): string
    {
        if (!$this->enabled) {
            return self::STATUS_DISABLED;
        }

        $now ??= DateTimeHelper::now();

        if ($this->dateTo !== null && $now > $this->dateTo) {
            return self::STATUS_ENDED;
        }

        if ($this->dateFrom !== null && $now < $this->dateFrom) {
            return self::STATUS_SCHEDULED;
        }

        return self::STATUS_ACTIVE;
    }

    /**
     * For a scheduled freeze, a short human-readable duration until it starts,
     * using only the largest unit (e.g. "1 day", "3 hours", "24 minutes").
     * Null when the freeze isn't scheduled.
     */
    public function getStartsIn(?DateTimeInterface $now = null): ?string
    {
        if ($this->getStatus($now) !== self::STATUS_SCHEDULED || $this->dateFrom === null) {
            return null;
        }

        $now ??= DateTimeHelper::now();
        $interval = $now->diff($this->dateFrom);

        $count = match (true) {
            $interval->days >= 1 => [$interval->days, 'day'],
            $interval->h >= 1 => [$interval->h, 'hour'],
            $interval->i >= 1 => [$interval->i, 'minute'],
            default => null,
        };

        if ($count === null) {
            return Craft::t('content-freeze', 'less than a minute');
        }

        [$value, $unit] = $count;

        return Craft::t('content-freeze', '{n, plural, =1{1 ' . $unit . '} other{# ' . $unit . 's}}', ['n' => $value]);
    }

    /**
     * For an active freeze, a short human-readable duration until it ends,
     * using only the largest unit (e.g. "1 day", "3 hours", "24 minutes").
     * Null when the freeze isn't active or has no end date.
     */
    public function getEndsIn(?DateTimeInterface $now = null): ?string
    {
        if ($this->getStatus($now) !== self::STATUS_ACTIVE || $this->dateTo === null) {
            return null;
        }

        $now ??= DateTimeHelper::now();
        $interval = $now->diff($this->dateTo);

        $count = match (true) {
            $interval->days >= 1 => [$interval->days, 'day'],
            $interval->h >= 1 => [$interval->h, 'hour'],
            $interval->i >= 1 => [$interval->i, 'minute'],
            default => null,
        };

        if ($count === null) {
            return Craft::t('content-freeze', 'less than a minute');
        }

        [$value, $unit] = $count;

        return Craft::t('content-freeze', '{n, plural, =1{1 ' . $unit . '} other{# ' . $unit . 's}}', ['n' => $value]);
    }

    /**
     * Returns the label/colour used for the status indicator in the CP.
     */
    public function getStatusLabel(): string
    {
        return match ($this->getStatus()) {
            self::STATUS_ACTIVE => Craft::t('content-freeze', 'Active'),
            self::STATUS_SCHEDULED => Craft::t('content-freeze', 'Scheduled'),
            self::STATUS_ENDED => Craft::t('content-freeze', 'Ended'),
            default => Craft::t('content-freeze', 'Disabled'),
        };
    }

    public function getStatusColor(): string
    {
        return match ($this->getStatus()) {
            self::STATUS_ACTIVE => 'green',
            self::STATUS_SCHEDULED => 'orange',
            default => 'disabled',
        };
    }
}
