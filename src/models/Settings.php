<?php

namespace bymayo\craftcontentfreeze\models;

use Craft;
use craft\base\Model;

/**
 * Content Freeze settings
 */
class Settings extends Model
{

    public $enable = false;
    public $dateFrom = '';
    public $dateTo = '';
    public $showNoticePanel = true;
    public $noticePanelHeading = 'Content Freeze';
    public $noticePanelText = 'Content editing is currently disabled due to a scheduled content freeze. Please try again after the freeze period has ended';
    public $showNoticeBar = true;
    public $noticeBarText = 'Content editing is currently disabled due to a scheduled content freeze. Please try again after the freeze period has ended';
    public $memberGroups = [];

}
