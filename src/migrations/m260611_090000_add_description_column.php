<?php

namespace bymayo\craftcontentfreeze\migrations;

use craft\db\Migration;

/**
 * Adds the optional per-freeze description column (shown on the dashboard
 * widget). Added only when missing, so it's safe on installs whose table was
 * created with the column already present.
 */
class m260611_090000_add_description_column extends Migration
{
    public const TABLE = '{{%contentfreeze_freezes}}';

    public function safeUp(): bool
    {
        if (!$this->db->columnExists(self::TABLE, 'description')) {
            $this->addColumn(self::TABLE, 'description', $this->text()->after('name'));
        }

        return true;
    }

    public function safeDown(): bool
    {
        if ($this->db->columnExists(self::TABLE, 'description')) {
            $this->dropColumn(self::TABLE, 'description');
        }

        return true;
    }
}
