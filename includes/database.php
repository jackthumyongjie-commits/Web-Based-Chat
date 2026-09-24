<?php
/**
 * WebConnect — PDO database connection
 */

declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );

        try {
            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            app_log('database', 'Connection failed: ' . $e->getMessage());
            if (PHP_SAPI === 'cli') {
                throw $e;
            }
            http_response_code(500);
            if (defined('APP_DEBUG') && APP_DEBUG) {
                exit('Database connection failed. Check configuration.');
            }
            exit('Service temporarily unavailable.');
        }

        return self::$pdo;
    }
}

function db(): PDO
{
    return Database::connection();
}
