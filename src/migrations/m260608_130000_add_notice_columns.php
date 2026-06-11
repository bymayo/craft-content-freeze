<?php

namespace bymayo\craftcontentfreeze\migrations;

use craft\db\Migration;

/**
 * Adds the per-freeze notice (pane/bar) override columns to the freezes table.
 * Each column is added only when missing, so this is safe on installs whose
 * table was created with these columns already present.
 */
class m260608_130000_add_notice_columns extends Migration
{
    public const TABLE = '{{%contentfreeze_freezes}}';

    public function safeUp(): bool
    {
        if (!$this->db->columnExists(self::TABLE, 'showNoticePane')) {
            $this->addColumn(self::TABLE, 'showNoticePane', $this->boolean()->notNull()->defaultValue(true)->after('dateTo'));
        }

        if (!$this->db->columnExists(self::TABLE, 'noticePaneHeading')) {
            $this->addColumn(self::TABLE, 'noticePaneHeading', $this->string()->after('showNoticePane'));
        }

        if (!$this->db->columnExists(self::TABLE, 'noticePaneText')) {
            $this->addColumn(self::TABLE, 'noticePaneText', $this->text()->after('noticePaneHeading'));
        }

        if (!$this->db->columnExists(self::TABLE, 'showNoticeBar')) {
            $this->addColumn(self::TABLE, 'showNoticeBar', $this->boolean()->notNull()->defaultValue(true)->after('noticePaneText'));
        }

        if (!$this->db->columnExists(self::TABLE, 'noticeBarText')) {
            $this->addColumn(self::TABLE, 'noticeBarText', $this->text()->after('showNoticeBar'));
        }

        return true;
    }

    public function safeDown(): bool
    {
        foreach (['noticeBarText', 'showNoticeBar', 'noticePaneText', 'noticePaneHeading', 'showNoticePane'] as $column) {
            if ($this->db->columnExists(self::TABLE, $column)) {
                $this->dropColumn(self::TABLE, $column);
            }
        }

        return true;
    }
}
