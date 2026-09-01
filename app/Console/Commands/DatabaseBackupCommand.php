<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * SQL dump to storage/app/backups — pure PHP (no shell / mysqldump).
 * Safe on Hostinger shared hosting if you ever run artisan via cron/SSH.
 */
class DatabaseBackupCommand extends Command
{
    protected $signature = 'db:backup
                            {--path= : Relative path under storage/app (default: backups/db-YYYYMMDD-HHMMSS.sql)}';

    protected $description = 'Export a database dump to storage/app/backups (SQLite file copy or MySQL SQL via PHP)';

    public function handle(): int
    {
        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");

        $relative = $this->option('path') ?: ('backups/db-'.now()->format('Ymd-His').'.sql');
        $relative = ltrim(str_replace('\\', '/', (string) $relative), '/');
        $absolute = storage_path('app/'.$relative);

        File::ensureDirectoryExists(dirname($absolute));

        try {
            return match ($driver) {
                'sqlite' => $this->backupSqlite($connection, $absolute, $relative),
                'mysql', 'mariadb' => $this->backupMysqlPhp($absolute, $relative),
                default => $this->failDriver($driver),
            };
        } catch (Throwable $e) {
            $this->error('Backup failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function failDriver(string $driver): int
    {
        $this->error("db:backup does not support driver [{$driver}].");

        return self::FAILURE;
    }

    private function backupSqlite(string $connection, string $absolute, string $relative): int
    {
        $database = config("database.connections.{$connection}.database");

        if (! is_string($database) || $database === '' || $database === ':memory:') {
            $this->error('SQLite database path is not a file (in-memory or missing).');

            return self::FAILURE;
        }

        if (! is_file($database)) {
            $this->error("SQLite file not found: {$database}");

            return self::FAILURE;
        }

        File::copy($database, $absolute);
        $this->info("SQLite backup written to storage/app/{$relative}");

        return self::SUCCESS;
    }

    private function backupMysqlPhp(string $absolute, string $relative): int
    {
        $pdo = DB::connection()->getPdo();
        $tables = DB::select('SHOW FULL TABLES WHERE Table_type = ?', ['BASE TABLE']);
        $dbName = (string) DB::getDatabaseName();
        $key = 'Tables_in_'.$dbName;

        $sql = '-- '.config('app.name', 'Portfolio OS')." db:backup\n";
        $sql .= '-- Generated: '.now()->toIso8601String()."\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $row) {
            $table = $row->{$key} ?? null;
            if (! is_string($table) || $table === '') {
                continue;
            }

            $safe = str_replace('`', '``', $table);
            $create = DB::selectOne('SHOW CREATE TABLE `'.$safe.'`');
            $createSql = $create->{'Create Table'} ?? null;
            if (! is_string($createSql)) {
                continue;
            }

            $sql .= "DROP TABLE IF EXISTS `{$safe}`;\n";
            $sql .= $createSql.";\n\n";

            foreach (DB::table($table)->cursor() as $data) {
                $cols = [];
                $vals = [];
                foreach ((array) $data as $col => $val) {
                    $cols[] = '`'.str_replace('`', '``', (string) $col).'`';
                    $vals[] = $val === null ? 'NULL' : $pdo->quote((string) $val);
                }
                $sql .= 'INSERT INTO `'.$safe.'` ('.implode(', ', $cols).') VALUES ('.implode(', ', $vals).");\n";
            }
            $sql .= "\n";
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        File::put($absolute, $sql);
        $this->info("MySQL export written to storage/app/{$relative}");

        return self::SUCCESS;
    }
}
