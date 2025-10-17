<?php

namespace App\Core;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Result;

class DbalMysqliAdapter
{
    private Connection $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    // === Query methods ===

    public function query(string $sql)
    {
        return $this->conn->executeQuery($sql);
    }

    public function multi_query(string $sql)
    {
        $queries = array_filter(array_map('trim', explode(';', $sql)));
        $results = [];
        foreach ($queries as $q) {
            if ($q !== '') {
                $results[] = $this->conn->executeQuery($q);
            }
        }
        return $results;
    }

    public function prepare(string $sql)
    {
        return $this->conn->prepare($sql);
    }

    public function real_escape_string(string $string): string
    {
        // Strip surrounding quotes returned by DBAL quote
        $quoted = $this->conn->quote($string);
        return substr($quoted, 1, -1);
    }

    public function begin_transaction(): void
    {
        $this->conn->beginTransaction();
    }

    public function commit(): void
    {
        $this->conn->commit();
    }

    public function rollback(): void
    {
        $this->conn->rollBack();
    }

    // === Fetch methods ===

    public function fetch_assoc($result): ?array
    {
        if ($result instanceof Result) {
            $row = $result->fetchAssociative();
            return $row === false ? null : $row;
        }
        return null;
    }

    public function fetch_all($result): array
    {
        if ($result instanceof Result) {
            return $result->fetchAllAssociative();
        }
        return [];
    }

    public function num_rows($result): int
    {
        if ($result instanceof Result) {
            return count($result->fetchAllAssociative());
        }
        return 0;
    }

    // === Connection info ===

    public function close(): void
    {
        // Doctrine doesn't have a direct close; use null workaround
        $this->conn = null;
    }

    public function error(): string
    {
        $info = $this->conn->errorInfo();
        return isset($info[2]) ? $info[2] : '';
    }

    public function getConnection(): Connection
    {
        return $this->conn;
    }
}



