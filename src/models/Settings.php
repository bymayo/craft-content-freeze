<?php

namespace bymayo\craftcontentfreeze\models;

use Craft;
use craft\base\Model;

/**
 * Content Freeze settings
 */
class Settings extends Model
{

    public $enabled = false;
    public $dateFrom = null;
    public $dateTo = null;
    public $showNoticePane = true;
    public $noticePaneHeading = 'Content Freeze';
    public $noticePaneText = 'Editing is currently paused as part of a scheduled content freeze. Viewing is still available, but changes can’t be made until the freeze is lifted.';
    public $showNoticeBar = true;
    public $noticeBarText = 'Editing is currently paused as part of a scheduled content freeze. Viewing is still available, but changes can’t be made until the freeze is lifted.';
    public $memberGroups = [];

}
