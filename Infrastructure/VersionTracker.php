<?php

namespace BitrixCdd\Infrastructure;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Connection;
use Bitrix\Main\Type\DateTime;

/**
 * Version Tracker for IBlock Configurations
 *
 * Manages version tracking table to optimize sync operations.
 * Table auto-creates if not exists.
 * Stores: iblock_code, version, synced_at
 */
class VersionTracker
{
    private const TABLE_NAME = 'cdd_iblock_versions';
    private Connection $connection;

    public function __construct()
    {
        $this->connection = Application::getConnection();
        $this->ensureTableExists();
    }

    /**
     * Ensure version tracking table exists (auto-create)
     */
    private function ensureTableExists(): void
    {
        $tableName = self::TABLE_NAME;

        // ORM не создаёт таблицы — используем raw SQL для DDL
        $tables = $this->connection->query("SHOW TABLES LIKE '{$tableName}'")->fetch();

        if (!$tables) {
            $sql = "
                CREATE TABLE IF NOT EXISTS {$tableName} (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    iblock_code VARCHAR(255) NOT NULL,
                    version VARCHAR(50) NOT NULL,
                    synced_at DATETIME NOT NULL,
                    demo_synced TINYINT(1) DEFAULT 0,
                    UNIQUE KEY unique_iblock_version (iblock_code, version),
                    INDEX idx_iblock_code (iblock_code)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";

            $this->connection->query($sql);
        } else {
            $this->ensureDemoSyncedColumnExists();
        }
    }

    /**
     * Ensure demo_synced column exists (for migration of existing tables)
     */
    private function ensureDemoSyncedColumnExists(): void
    {
        $tableName = self::TABLE_NAME;

        $sql = "SHOW COLUMNS FROM {$tableName} LIKE 'demo_synced'";
        $result = $this->connection->query($sql)->fetch();

        if ($result) {
            return;
        }

        try {
            $sql = "ALTER TABLE {$tableName} ADD COLUMN demo_synced TINYINT(1) DEFAULT 0";
            $this->connection->query($sql);
        } catch (\Exception $e) {
            // Column already exists (added by concurrent process), ignore
        }
    }

    /**
     * Check if version is already registered for iblock
     *
     * @param string $iblockCode IBlock code
     * @param string $version Version to check
     * @return bool True if version registered
     */
    public function isVersionRegistered(string $iblockCode, string $version): bool
    {
        $count = VersionTable::getCount([
            '=iblock_code' => $iblockCode,
            '=version' => $version,
        ]);

        return $count > 0;
    }

    /**
     * Register version for iblock
     *
     * @param string $iblockCode IBlock code
     * @param string $version Version to register
     * @param bool $demoSynced Whether demo data is synced (default: false)
     * @return void
     */
    public function registerVersion(string $iblockCode, string $version, bool $demoSynced = false): void
    {
        // Проверяем, существует ли уже запись
        $existing = VersionTable::getList([
            'filter' => [
                '=iblock_code' => $iblockCode,
                '=version' => $version,
            ],
            'select' => ['id'],
            'limit' => 1,
        ])->fetch();

        if ($existing) {
            // Обновляем
            VersionTable::update($existing['id'], [
                'synced_at' => new DateTime(),
                'demo_synced' => $demoSynced ? 1 : 0,
            ]);
        } else {
            // Создаём
            VersionTable::add([
                'iblock_code' => $iblockCode,
                'version' => $version,
                'synced_at' => new DateTime(),
                'demo_synced' => $demoSynced ? 1 : 0,
            ]);
        }
    }

    /**
     * Check if demo data is synced for version
     *
     * @param string $iblockCode IBlock code
     * @param string $version Version to check
     * @return bool True if demo data synced
     */
    public function isDemoSynced(string $iblockCode, string $version): bool
    {
        $row = VersionTable::getList([
            'filter' => [
                '=iblock_code' => $iblockCode,
                '=version' => $version,
            ],
            'select' => ['demo_synced'],
            'limit' => 1,
        ])->fetch();

        return !empty($row['demo_synced']);
    }

    /**
     * Mark demo data as synced for version
     *
     * @param string $iblockCode IBlock code
     * @param string $version Version
     * @return void
     */
    public function markDemoSynced(string $iblockCode, string $version): void
    {
        $row = VersionTable::getList([
            'filter' => [
                '=iblock_code' => $iblockCode,
                '=version' => $version,
            ],
            'select' => ['id'],
            'limit' => 1,
        ])->fetch();

        if ($row) {
            VersionTable::update($row['id'], [
                'demo_synced' => 1,
            ]);
        }
    }

    /**
     * Get current (latest) version for iblock
     *
     * @param string $iblockCode IBlock code
     * @return string|null Current version or null if not registered
     */
    public function getCurrentVersion(string $iblockCode): ?string
    {
        $row = VersionTable::getList([
            'filter' => ['=iblock_code' => $iblockCode],
            'select' => ['version'],
            'order' => ['synced_at' => 'DESC', 'id' => 'DESC'],
            'limit' => 1,
        ])->fetch();

        return $row['version'] ?? null;
    }

    /**
     * Get all registered versions for iblock
     *
     * @param string $iblockCode IBlock code
     * @return array Array of versions
     */
    public function getAllVersions(string $iblockCode): array
    {
        $result = VersionTable::getList([
            'filter' => ['=iblock_code' => $iblockCode],
            'select' => ['version', 'synced_at'],
            'order' => ['synced_at' => 'DESC', 'id' => 'DESC'],
        ]);

        $versions = [];
        while ($row = $result->fetch()) {
            $versions[] = $row;
        }

        return $versions;
    }

    /**
     * Delete all version records for iblock
     *
     * @param string $iblockCode IBlock code
     * @return void
     */
    public function deleteVersions(string $iblockCode): void
    {
        $result = VersionTable::getList([
            'filter' => ['=iblock_code' => $iblockCode],
            'select' => ['id'],
        ]);

        while ($row = $result->fetch()) {
            VersionTable::delete($row['id']);
        }
    }

    /**
     * Check if version table exists
     *
     * @return bool
     */
    public function tableExists(): bool
    {
        $tableName = self::TABLE_NAME;
        $tables = $this->connection->query("SHOW TABLES LIKE '{$tableName}'")->fetch();

        return !empty($tables);
    }
}
