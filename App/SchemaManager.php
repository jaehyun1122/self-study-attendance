<?php

declare(strict_types=1);

namespace App;

use PDOException;
use Throwable;

require_once __DIR__ . '/Database.php';

final class SchemaManager
{
    private const SCHEMA_VERSION = 10904;

    private bool $ready = false;

    public function __construct(private readonly Database $database)
    {
    }

    public function migrate(): void
    {
        if ($this->ready) {
            return;
        }

        $pdo = $this->database->pdo();
        $version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();

        if ($version >= self::SCHEMA_VERSION) {
            $this->ready = true;
            return;
        }

        $pdo->exec('BEGIN IMMEDIATE');

        try {
            $version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();

            if ($version >= self::SCHEMA_VERSION) {
                $pdo->commit();
                $this->ready = true;
                return;
            }

            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS app_settings (
                    setting_key TEXT PRIMARY KEY,
                    setting_value TEXT NOT NULL,
                    updated_at TEXT NOT NULL
                )"
            );
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS auth_rate_limits (
                    scope TEXT NOT NULL,
                    identifier TEXT NOT NULL,
                    attempts INTEGER NOT NULL DEFAULT 0,
                    window_started_at TEXT NOT NULL,
                    blocked_until TEXT,
                    updated_at TEXT NOT NULL,
                    PRIMARY KEY (scope, identifier)
                )"
            );
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_auth_rate_limits_updated_at ON auth_rate_limits(updated_at)');

            $hasAdminTokens = ((int) $pdo
                ->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'admin_tokens'")
                ->fetchColumn()) > 0;

            if ($hasAdminTokens) {
                $tokenColumns = [];
                foreach ($pdo->query('PRAGMA table_info(admin_tokens)')->fetchAll() as $column) {
                    if (isset($column['name'])) {
                        $tokenColumns[(string) $column['name']] = true;
                    }
                }

                $tokenColumnDefinitions = [
                    'last_seen_at' => 'last_seen_at TEXT',
                    'ip_address' => 'ip_address TEXT',
                    'user_agent' => 'user_agent TEXT',
                ];

                foreach ($tokenColumnDefinitions as $name => $definition) {
                    if (!isset($tokenColumns[$name])) {
                        $pdo->exec("ALTER TABLE admin_tokens ADD COLUMN {$definition}");
                    }
                }

                $pdo->exec('CREATE INDEX IF NOT EXISTS idx_admin_tokens_expired_at ON admin_tokens(expired_at)');
            }

            $hasAttendance = ((int) $pdo
                ->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'attendance'")
                ->fetchColumn()) > 0;

            if ($hasAttendance) {
                $columns = [];
                foreach ($pdo->query('PRAGMA table_info(attendance)')->fetchAll() as $column) {
                    if (isset($column['name'])) {
                        $columns[(string) $column['name']] = true;
                    }
                }

                $addColumn = function (string $name, string $definition) use ($pdo, &$columns): void {
                    if (isset($columns[$name])) {
                        return;
                    }

                    try {
                        $pdo->exec("ALTER TABLE attendance ADD COLUMN {$definition}");
                    } catch (PDOException $exception) {
                        if (!self::isIgnorableConflict($exception)) {
                            throw $exception;
                        }
                    }

                    $columns[$name] = true;
                };

                $addColumn('location_status', "location_status TEXT NOT NULL DEFAULT 'unchecked'");
                $addColumn('location_latitude', 'location_latitude REAL');
                $addColumn('location_longitude', 'location_longitude REAL');
                $addColumn('location_accuracy', 'location_accuracy REAL');
                $addColumn('location_distance_meters', 'location_distance_meters REAL');
                $addColumn('location_message', 'location_message TEXT');
                $addColumn('location_checked_at', 'location_checked_at TEXT');
                $addColumn('location_approved_at', 'location_approved_at TEXT');
                $pdo->exec('CREATE INDEX IF NOT EXISTS idx_attendance_attend_date ON attendance(attend_date)');
                $pdo->exec('CREATE INDEX IF NOT EXISTS idx_attendance_location_status ON attendance(location_status)');
                $pdo->exec('CREATE INDEX IF NOT EXISTS idx_attendance_created_at ON attendance(created_at)');
            }

            $pdo->exec('PRAGMA user_version = ' . self::SCHEMA_VERSION);
            $pdo->commit();
            $this->ready = true;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public static function isIgnorableConflict(Throwable $exception): bool
    {
        if (!$exception instanceof PDOException) {
            return false;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'duplicate column name')
            || str_contains($message, 'already exists');
    }
}
