<?php

namespace bymayo\craftcontentfreeze\models;

use Craft;
use craft\base\Model;

/**
 * Content Freeze settings
 */
class Settings extends Model
{

    public bool $showNoticePane = true;
    public string $noticePaneHeading = 'Content Freeze';
    public string $noticePaneText = 'Editing is currently paused as part of a scheduled content freeze. Viewing is still available, but changes can\'t be made until the freeze is lifted.';
    public bool $showNoticeBar = true;
    public string $noticeBarText = 'Editing is currently paused as part of a scheduled content freeze. Viewing is still available, but changes can\'t be made until the freeze is lifted.';

    /**
     * Whether to queue a database backup when a freeze becomes active.
     */
    public bool $backupOnFreeze = false;

    /**
     * Extra permission names to preserve when cloning a "view only" group, on top
     * of the built-in support for Craft core / Commerce / Freeform / Formie. Use
     * this (via config.php) to add view/read/access permissions for other plugins.
     *
     * @var string[]
     */
    public array $viewOnlyKeepPermissions = [];

    public function defineRules(): array
    {
        return [
            [['showNoticePane', 'showNoticeBar', 'backupOnFreeze'], 'boolean'],
            [['noticePaneHeading', 'noticePaneText', 'noticeBarText'], 'string'],
            [['viewOnlyKeepPermissions'], 'safe'],
        ];
    }

}
