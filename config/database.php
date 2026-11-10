<?php
/**
 * PDO Database Connection
 * Single shared connection object for the whole app.
 */

require_once __DIR__ . '/constants.php';

class Database
{
    private static ?PDO $instance = null;

    // Update these to match your local XAMPP MySQL setup
    private const DB_HOST = '127.0.0.1';
    private const DB_NAME = 'hongsing_dms';
    private const DB_USER = 'root';
    private const DB_PASS = '';
    private const DB_CHARSET = 'utf8mb4';

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                self::DB_HOST,
                self::DB_NAME,
                self::DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, self::DB_USER, self::DB_PASS, $options);
            } catch (PDOException $e) {
                // In production, log this instead of exposing the message
                die('Database connection failed: ' . $e->getMessage());
            }
        }

        return self::$instance;
    }
}