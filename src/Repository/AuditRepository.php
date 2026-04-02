<?php

/**
 * Studio Todo Bundle for Pimcore 12+
 *
 * @license MIT
 * @author Mukesh Chauhan
 */

declare(strict_types=1);

namespace ChauhanMukesh\StudioTodoBundle\Repository;

use ChauhanMukesh\StudioTodoBundle\Installer\Installer;
use Doctrine\DBAL\Connection;

/**
 * Audit Repository - Data access layer for audit logs
 *
 * Manages audit trail for all todo operations
 */
class AuditRepository
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    /**
     * Log an audit entry
     */
    public function log(
        int $todoId,
        string $action,
        ?string $fieldName = null,
        mixed $oldValue = null,
        mixed $newValue = null,
        ?int $userId = null
    ): int {
        $data = [
            'todo_id' => $todoId,
            'action' => $action,
            'field_name' => $fieldName,
            'old_value' => $this->serializeValue($oldValue),
            'new_value' => $this->serializeValue($newValue),
            'user_id' => $userId,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];

        $this->connection->insert(Installer::TABLE_TODO_AUDIT_LOG, $data);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * Get audit log for a specific todo
     */
    public function getByTodoId(int $todoId, int $limit = 100, int $offset = 0): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from(Installer::TABLE_TODO_AUDIT_LOG)
            ->where('todo_id = :todo_id')
            ->setParameter('todo_id', $todoId)
            ->orderBy('created_at', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * Get all audit entries with optional filtering
     */
    public function findAll(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from(Installer::TABLE_TODO_AUDIT_LOG)
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->orderBy('created_at', 'DESC');

        if (isset($filters['todo_id'])) {
            $qb->andWhere('todo_id = :todo_id')
                ->setParameter('todo_id', $filters['todo_id']);
        }

        if (isset($filters['action'])) {
            $qb->andWhere('action = :action')
                ->setParameter('action', $filters['action']);
        }

        if (isset($filters['user_id'])) {
            $qb->andWhere('user_id = :user_id')
                ->setParameter('user_id', $filters['user_id']);
        }

        if (isset($filters['from_date'])) {
            $qb->andWhere('created_at >= :from_date')
                ->setParameter('from_date', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $qb->andWhere('created_at <= :to_date')
                ->setParameter('to_date', $filters['to_date']);
        }

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * Delete old audit logs (for cleanup)
     */
    public function deleteOlderThan(\DateTimeInterface $date): int
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->delete(Installer::TABLE_TODO_AUDIT_LOG)
            ->where('created_at < :date')
            ->setParameter('date', $date->format('Y-m-d H:i:s'));

        return $qb->executeStatement();
    }

    /**
     * Count audit entries
     */
    public function count(array $filters = []): int
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('COUNT(*) as count')
            ->from(Installer::TABLE_TODO_AUDIT_LOG);

        if (isset($filters['todo_id'])) {
            $qb->andWhere('todo_id = :todo_id')
                ->setParameter('todo_id', $filters['todo_id']);
        }

        if (isset($filters['action'])) {
            $qb->andWhere('action = :action')
                ->setParameter('action', $filters['action']);
        }

        return (int) $qb->executeQuery()->fetchOne();
    }

    /**
     * Serialize a value for storage
     */
    private function serializeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        return json_encode($value);
    }
}
