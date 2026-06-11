<?php

namespace bymayo\craftcontentfreeze\migrations;

use craft\db\Migration;

/**
 * Adds the per-freeze "notify users" toggle column. Added only when missing, so
 * it's safe on installs whose table already has the column.
 */
class m260611_100000_add_notify_users_column extends Migration
{
    public const TABLE = '{{%contentfreeze_freezes}}';

    public function safeUp(): bool
    {
        if (!$this->db->columnExists(self::TABLE, 'notifyUsers')) {
            $this->addColumn(self::TABLE, 'notifyUsers', $this->boolean()->notNull()->defaultValue(false)->after('enabled'));
        }

        return true;
    }

    public function safeDown(): bool
    {
        if ($this->db->columnExists(self::TABLE, 'notifyUsers')) {
            $this->dropColumn(self::TABLE, 'notifyUsers');
        }

        return true;
    }
}
