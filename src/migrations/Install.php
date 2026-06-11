<?php

namespace bymayo\craftcontentfreeze\migrations;

use craft\db\Migration;

/**
 * Install migration.
 */
class Install extends Migration
{
    public const TABLE = '{{%contentfreeze_freezes}}';

    public function safeUp(): bool
    {
        if (!$this->db->tableExists(self::TABLE)) {
            $this->createTable(self::TABLE, [
                'id' => $this->primaryKey(),
                'name' => $this->string()->notNull(),
                'description' => $this->text(),
                'enabled' => $this->boolean()->notNull()->defaultValue(true),
                'notifyUsers' => $this->boolean()->notNull()->defaultValue(false),
                'dateFrom' => $this->dateTime()->null(),
                'dateTo' => $this->dateTime()->null(),
                'showNoticePane' => $this->boolean()->notNull()->defaultValue(true),
                'noticePaneHeading' => $this->string(),
                'noticePaneText' => $this->text(),
                'showNoticeBar' => $this->boolean()->notNull()->defaultValue(true),
                'noticeBarText' => $this->text(),
                'userGroups' => $this->text(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, self::TABLE, ['enabled']);
        }

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists(self::TABLE);

        return true;
    }
}
