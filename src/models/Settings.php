<?php

namespace bymayo\craftcontentfreeze\models;

use Craft;
use craft\base\Model;

/**
 * Content Freeze settings
 */
class Settings extends Model
{

    public bool $enabled = false;
    public mixed $dateFrom = null;
    public mixed $dateTo = null;
    public bool $showNoticePane = true;
    public string $noticePaneHeading = 'Content Freeze';
    public string $noticePaneText = 'Editing is currently paused as part of a scheduled content freeze. Viewing is still available, but changes can\'t be made until the freeze is lifted.';
    public bool $showNoticeBar = true;
    public string $noticeBarText = 'Editing is currently paused as part of a scheduled content freeze. Viewing is still available, but changes can\'t be made until the freeze is lifted.';
    public array $userGroups = [];

    public function defineRules(): array
    {
        return [
            [['enabled', 'showNoticePane', 'showNoticeBar'], 'boolean'],
            [['noticePaneHeading', 'noticePaneText', 'noticeBarText'], 'string'],
            [['dateFrom', 'dateTo'], 'safe'],
            [['userGroups'], 'safe'],
        ];
    }

}
