<?php

declare(strict_types=1);

/**
 * Breaks the MySQL/MariaDB purge down into its phases, to show where the time goes: the DELETE
 * statements, the identity reset that DELETE makes necessary, and the TRUNCATE statements they
 * replace.
 *
 * Usage: php bin/purge-phases.php <pdo-dsn> <user> <password> [cycles]
 */
$dsn = $argv[1] ?? 'mysql:host=mariadb;port=3306;dbname=db_test';
$user = $argv[2] ?? 'db_test';
$password = $argv[3] ?? 'db_test';
$cycles = (int) ($argv[4] ?? 30);

$pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$identityTables = [];
foreach ($tables as $table) {
    $row = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND EXTRA LIKE '%auto_increment%'"
    )->fetchColumn();
    if ($row > 0) {
        $identityTables[] = $table;
    }
}

printf("%d tables, %d of them with an auto_increment column\n", \count($tables), \count($identityTables));

/** Fills every table with a few rows, so the cleanup has something to do. */
$seed = static function () use ($pdo, $tables): void {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tables as $table) {
        $columns = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        $names = [];
        $values = [];
        foreach ($columns as $column) {
            if (str_contains($column['Extra'], 'auto_increment')) {
                continue;
            }
            $names[] = "`{$column['Field']}`";
            $values[] = match (true) {
                str_contains($column['Type'], 'int') => '1',
                str_contains($column['Type'], 'json') => "'{}'",
                default => "'x'",
            };
        }
        $sql = [] === $names
            ? "INSERT INTO `$table` VALUES ()"
            : 'INSERT INTO `'.$table.'` ('.implode(',', $names).') VALUES ('.implode(',', $values).')';

        for ($i = 0; $i < 5; ++$i) {
            try {
                $pdo->exec($sql);
            } catch (PDOException) {
                // a table we cannot trivially fill contributes no rows, that is fine
                break;
            }
        }
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
};

$time = static function (callable $fn) use ($cycles, $seed): float {
    $total = 0.0;
    for ($i = 0; $i < $cycles; ++$i) {
        $seed();
        $start = microtime(true);
        $fn();
        $total += microtime(true) - $start;
    }

    return $total / $cycles * 1000;
};

$deletes = static function () use ($pdo, $tables): void {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tables as $table) {
        $pdo->exec("DELETE FROM `$table`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
};

$deletesAndReset = static function () use ($pdo, $tables, $identityTables): void {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tables as $table) {
        $pdo->exec("DELETE FROM `$table`");
    }
    foreach ($identityTables as $table) {
        $pdo->exec("ALTER TABLE `$table` AUTO_INCREMENT = 1");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
};

$truncates = static function () use ($pdo, $tables): void {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tables as $table) {
        $pdo->exec("TRUNCATE `$table`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
};

// Most tests only write to a handful of tables, so most of the ALTER statements reset a counter
// that is already 1. Skipping those needs one query, but the AUTO_INCREMENT column of
// information_schema is cached in MySQL 8+, so the cache has to be disabled for the answer to be
// current.
$deletesAndSelectiveReset = static function () use ($pdo, $tables): void {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tables as $table) {
        $pdo->exec("DELETE FROM `$table`");
    }

    $dirty = $pdo->query(
        'SELECT TABLE_NAME FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND AUTO_INCREMENT > 1'
    )->fetchAll(PDO::FETCH_COLUMN);

    foreach ($dirty as $table) {
        $pdo->exec("ALTER TABLE `$table` AUTO_INCREMENT = 1");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
};

try {
    $pdo->exec('SET SESSION information_schema_stats_expiry = 0');
} catch (PDOException) {
    // MariaDB has no such setting, its values are not cached
}

printf("mean over %d cycles, ms per purge:\n", $cycles);
printf("  DELETE only                  %7.2f\n", $time($deletes));
printf("  DELETE + identity reset      %7.2f\n", $time($deletesAndReset));
printf("  DELETE + selective reset     %7.2f\n", $time($deletesAndSelectiveReset));
printf("  TRUNCATE                     %7.2f\n", $time($truncates));
