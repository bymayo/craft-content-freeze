<?php

namespace bymayo\craftcontentfreeze\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\helpers\StringHelper;

/**
 * Creates the freezes table for installs upgrading from the single-freeze
 * (settings-based) version, and imports the existing freeze configuration into
 * one freeze record.
 */
class m260608_120000_create_freezes_table extends Migration
{
    public const TABLE = '{{%contentfreeze_freezes}}';

    public function safeUp(): bool
    {
        if (!$this->db->tableExists(self::TABLE)) {
            $this->createTable(self::TABLE, [
                'id' => $this->primaryKey(),
                'name' => $this->string()->notNull(),
                'enabled' => $this->boolean()->notNull()->defaultValue(true),
                'dateFrom' => $this->dateTime()->null(),
                'dateTo' => $this->dateTime()->null(),
                'userGroups' => $this->text(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, self::TABLE, ['enabled']);
        }

        $this->importLegacyFreeze();

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists(self::TABLE);

        return true;
    }

    /**
     * Imports the previous single-freeze settings into one freeze record.
     *
     * Only runs when the table is empty and the old settings describe a
     * configured freeze, so re-running migrations never duplicates the row.
     */
    private function importLegacyFreeze(): void
    {
        // Don't import if any freeze already exists.
        if ((new Query())->from(self::TABLE)->exists()) {
            return;
        }

        $settings = Craft::$app->getProjectConfig()->get('plugins.content-freeze.settings') ?? [];

        $userGroups = $settings['userGroups'] ?? [];
        $enabled = (bool) ($settings['enabled'] ?? false);
        $dateFrom = DateTimeHelper::toDateTime($settings['dateFrom'] ?? null) ?: null;
        $dateTo = DateTimeHelper::toDateTime($settings['dateTo'] ?? null) ?: null;

        $hasMapping = false;
        foreach ($userGroups as $group) {
            if (!empty($group['enabled']) && !empty($group['contentFreezeGroup'])) {
                $hasMapping = true;
                break;
            }
        }

        // Nothing meaningful to import.
        if (!$hasMapping && !$enabled && $dateFrom === null && $dateTo === null) {
            return;
        }

        $now = DateTimeHelper::now();

        $this->insert(self::TABLE, [
            'name' => 'Imported Freeze',
            'enabled' => $enabled,
            'dateFrom' => $dateFrom ? Db::prepareDateForDb($dateFrom) : null,
            'dateTo' => $dateTo ? Db::prepareDateForDb($dateTo) : null,
            'userGroups' => Json::encode($userGroups),
            'dateCreated' => Db::prepareDateForDb($now),
            'dateUpdated' => Db::prepareDateForDb($now),
            'uid' => StringHelper::UUID(),
        ]);
    }
}
