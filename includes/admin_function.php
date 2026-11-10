<?php

require_once __DIR__ . '/../config/database.php';

class AdminManager
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Count admins
     */
    public function count(string $search = '', string $role = ''): int
    {
        $sql = "SELECT COUNT(*) FROM system_user WHERE 1";

        $params = [];

        if ($search !== '') {
            $sql .= " AND (full_name LIKE :search OR email LIKE :search)";
            $params['search'] = "%{$search}%";
        }

        if ($role !== '') {
            $sql .= " AND role = :role";
            $params['role'] = $role;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Get admin list
     */
    public function getAll(
        string $search = '',
        string $role = '',
        int $limit = 10,
        int $offset = 0
    ): array {

        $sql = "
            SELECT *
            FROM system_user
            WHERE 1
        ";

        $params = [];

        if ($search !== '') {
            $sql .= " AND (full_name LIKE :search OR email LIKE :search)";
            $params['search'] = "%{$search}%";
        }

        if ($role !== '') {
            $sql .= " AND role=:role";
            $params['role'] = $role;
        }

        $sql .= "
            ORDER BY created_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Get one admin
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM system_user
            WHERE user_id=?
        ");

        $stmt->execute([$id]);

        $admin = $stmt->fetch();

        return $admin ?: null;
    }

    /**
     * Check duplicate email
     */
    public function emailExists(string $email, ?int $ignoreId = null): bool
    {
        if ($ignoreId) {

            $stmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM system_user
                WHERE email=?
                AND user_id!=?
            ");

            $stmt->execute([$email, $ignoreId]);

        } else {

            $stmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM system_user
                WHERE email=?
            ");

            $stmt->execute([$email]);
        }

        return $stmt->fetchColumn() > 0;
    }

}