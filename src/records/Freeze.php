<?php

namespace bymayo\craftcontentfreeze\records;

use craft\db\ActiveRecord;

/**
 * Freeze record
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property bool $enabled
 * @property bool $notifyUsers
 * @property string|null $dateFrom
 * @property string|null $dateTo
 * @property bool $showNoticePane
 * @property string|null $noticePaneHeading
 * @property string|null $noticePaneText
 * @property bool $showNoticeBar
 * @property string|null $noticeBarText
 * @property string|null $userGroups
 */
class Freeze extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%contentfreeze_freezes}}';
    }
}
