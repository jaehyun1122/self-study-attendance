<?php

declare(strict_types=1);

namespace App;

use PDO;
use RuntimeException;

require_once __DIR__ . '/Config.php';

final class Database
{
    private ?PDO $pdo = null;

    public function __construct(private readonly Config $config)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        if (!extension_loaded('pdo_sqlite')) {
            throw new RuntimeException('pdo_sqlite 확장이 필요합니다.');
        }

        $databasePath = $this->config->string('database_path');
        $directory = dirname($databasePath);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('data 폴더를 생성할 수 없습니다.');
        }

        $this->pdo = new PDO('sqlite:' . $databasePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $journalMode = strtolower((string) $this->pdo->query('PRAGMA journal_mode')->fetchColumn());

        if ($journalMode !== 'wal') {
            $this->pdo->exec('PRAGMA journal_mode = WAL');
        }

        $this->pdo->exec('PRAGMA synchronous = NORMAL');

        return $this->pdo;
    }
}
