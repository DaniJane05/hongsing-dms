<?php

require_once __DIR__ . '/../config/database.php';

if (!function_exists('logAudit')) {

    function logAudit(
        int $userId,
        string $action,
        string $module,
        int $recordId = 0, // kept for compatibility
        string $description = ''
    ): void {

        $db = Database::getConnection();

        $stmt = $db->prepare("
            INSERT INTO audit_log
            (
                user_id,
                full_name,
                role,
                action,
                module,
                description,
                ip_address
            )
            VALUES
            (
                ?,?,?,?,?,?,?
            )
        ");

        $stmt->execute([

            $userId,

            $_SESSION['full_name'] ?? 'Unknown',

            $_SESSION['role'] ?? 'Unknown',

            strtoupper($action),

            $module,

            $description,

            $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'

        ]);

    }

}