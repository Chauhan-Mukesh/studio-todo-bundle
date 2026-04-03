<?php

/**
 * Studio Todo Bundle for Pimcore 12+
 *
 * @license MIT
 * @author Mukesh Chauhan
 */

declare(strict_types=1);

namespace ChauhanMukesh\StudioTodoBundle\Repository;

use ChauhanMukesh\StudioTodoBundle\Enum\TodoPriority;
use ChauhanMukesh\StudioTodoBundle\Enum\TodoStatus;
use ChauhanMukesh\StudioTodoBundle\Installer\Installer;
use ChauhanMukesh\StudioTodoBundle\Model\TodoItem;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Todo Repository - Data access layer for todo items
 *
 * Provides methods for CRUD operations and queries
 */
class TodoRepository
{
    /** Column names that are permitted as ORDER BY targets to prevent SQL injection */
    private const ALLOWED_SORT_COLUMNS = [
        'created_at', 'updated_at', 'title', 'status', 'priority',
        'due_date', 'position', 'category', 'assigned_to_user_id',
    ];

    /** Maximum character length accepted for full-text search values */
    private const SEARCH_MAX_LENGTH = 200;
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    /**
     * Find todo by ID
     *
     * @param bool $includeDeleted When true, soft-deleted items are also returned
     * @return TodoItem|null The todo item or null if not found
     */
    public function findById(int $id, bool $includeDeleted = false): ?TodoItem
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from(Installer::TABLE_TODO_ITEMS)
            ->where('id = :id')
            ->setParameter('id', $id);

        if (!$includeDeleted) {
            $qb->andWhere('deleted_at IS NULL');
        }

        $result = $qb->executeQuery()->fetchAssociative();

        return $result ? TodoItem::fromArray($result) : null;
    }

    /**
     * Find all todos with optional filtering
     *
     * @param array<string, mixed> $filters
     * @return TodoItem[]
     */
    public function findAll(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $qb = $this->createFilteredQuery($filters);

        $sortColumn = $filters['sort'] ?? 'created_at';
        if (!in_array($sortColumn, self::ALLOWED_SORT_COLUMNS, true)) {
            $sortColumn = 'created_at';
        }

        $qb->setMaxResults($limit)
            ->setFirstResult($offset)
            ->orderBy($sortColumn, $filters['order'] ?? 'DESC');

        $results = $qb->executeQuery()->fetchAllAssociative();

        return array_map(fn ($row) => TodoItem::fromArray($row), $results);
    }

    /**
     * Count todos with optional filtering
     *
     * @param array<string, mixed> $filters
     */
    public function count(array $filters = []): int
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('COUNT(*) as cnt')
            ->from(Installer::TABLE_TODO_ITEMS);

        if (!isset($filters['include_deleted']) || !$filters['include_deleted']) {
            $qb->andWhere('deleted_at IS NULL');
        }

        if (isset($filters['status'])) {
            $qb->andWhere('status = :status')->setParameter('status', $filters['status']);
        }

        if (isset($filters['priority'])) {
            $qb->andWhere('priority = :priority')->setParameter('priority', $filters['priority']);
        }

        if (isset($filters['assigned_to_user_id'])) {
            $qb->andWhere('assigned_to_user_id = :assigned_to')->setParameter('assigned_to', $filters['assigned_to_user_id']);
        }

        if (isset($filters['category'])) {
            $qb->andWhere('category = :category')->setParameter('category', $filters['category']);
        }

        if (isset($filters['overdue']) && $filters['overdue']) {
            $qb->andWhere('due_date < :now')
                ->andWhere('status NOT IN (:closed_statuses)')
                ->setParameter('now', (new \DateTimeImmutable())->format('Y-m-d H:i:s'))
                ->setParameter('closed_statuses', [TodoStatus::Completed->value, TodoStatus::Cancelled->value], ArrayParameterType::STRING);
        }

        if (isset($filters['search'])) {
            $searchValue = substr((string) $filters['search'], 0, self::SEARCH_MAX_LENGTH);
            $qb->andWhere('(title LIKE :search OR description LIKE :search)')->setParameter('search', '%' . $searchValue . '%');
        }

        return (int) $qb->executeQuery()->fetchOne();
    }

    /**
     * Create a new todo item
     *
     * @param array<string, mixed> $data
     * @throws \Doctrine\DBAL\Exception On database error
     * @return int The ID of the newly created todo
     */
    public function create(array $data): int
    {
        $now = new \DateTimeImmutable();

        $insertData = [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? TodoStatus::Open->value,
            'workflow_state' => $data['workflow_state'] ?? null,
            'priority' => $data['priority'] ?? TodoPriority::Medium->value,
            'related_element_id' => $data['related_element_id'] ?? null,
            'related_element_type' => $data['related_element_type'] ?? null,
            'related_class' => $data['related_class'] ?? null,
            'assigned_to_user_id' => $data['assigned_to_user_id'] ?? null,
            'created_by_user_id' => $data['created_by_user_id'] ?? null,
            'updated_by_user_id' => $data['updated_by_user_id'] ?? null,
            'due_date' => isset($data['due_date']) ? $this->formatDateTime($data['due_date']) : null,
            'completed_at' => null,
            'created_at' => $now->format('Y-m-d H:i:s'),
            'updated_at' => $now->format('Y-m-d H:i:s'),
            'deleted_at' => null,
            'position' => $data['position'] ?? 0,
            'category' => $data['category'] ?? null,
            'meta' => isset($data['meta']) ? json_encode($data['meta']) : null,
        ];

        $this->connection->insert(Installer::TABLE_TODO_ITEMS, $insertData);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * Update an existing todo item
     *
     * @param array<string, mixed> $data
     * @throws \Doctrine\DBAL\Exception On database error
     * @return bool True if update succeeded, false if nothing was updated
     */
    public function update(int $id, array $data): bool
    {
        $updateData = [];

        $allowedFields = [
            'title', 'description', 'status', 'workflow_state', 'priority',
            'related_element_id', 'related_element_type', 'related_class',
            'assigned_to_user_id', 'updated_by_user_id', 'position', 'category',
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        if (isset($data['due_date'])) {
            $updateData['due_date'] = $this->formatDateTime($data['due_date']);
        }

        if (isset($data['completed_at'])) {
            $updateData['completed_at'] = $this->formatDateTime($data['completed_at']);
        }

        if (isset($data['meta'])) {
            $updateData['meta'] = json_encode($data['meta']);
        }

        $updateData['updated_at'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $result = $this->connection->update(
            Installer::TABLE_TODO_ITEMS,
            $updateData,
            ['id' => $id]
        );

        return $result > 0;
    }

    /**
     * Soft delete a todo item
     */
    public function softDelete(int $id): bool
    {
        return $this->connection->update(
            Installer::TABLE_TODO_ITEMS,
            ['deleted_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')],
            ['id' => $id]
        ) > 0;
    }

    /**
     * Restore a soft-deleted todo item
     */
    public function restore(int $id): bool
    {
        return $this->connection->update(
            Installer::TABLE_TODO_ITEMS,
            ['deleted_at' => null],
            ['id' => $id]
        ) > 0;
    }

    /**
     * Permanently delete a todo item
     */
    public function hardDelete(int $id): bool
    {
        return $this->connection->delete(
            Installer::TABLE_TODO_ITEMS,
            ['id' => $id]
        ) > 0;
    }

    /**
     * Find overdue todos
     *
     * @return TodoItem[]
     */
    public function findOverdue(): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from(Installer::TABLE_TODO_ITEMS)
            ->where('deleted_at IS NULL')
            ->andWhere('due_date < :now')
            ->andWhere('status NOT IN (:closed_statuses)')
            ->setParameter('now', (new \DateTimeImmutable())->format('Y-m-d H:i:s'))
            ->setParameter('closed_statuses', [
                TodoStatus::Completed->value,
                TodoStatus::Cancelled->value,
            ], ArrayParameterType::STRING);

        $results = $qb->executeQuery()->fetchAllAssociative();

        return array_map(fn ($row) => TodoItem::fromArray($row), $results);
    }

    /**
     * Find todos by user
     *
     * @return TodoItem[]
     */
    public function findByUser(int $userId, ?string $status = null): array
    {
        $filters = [
            'assigned_to_user_id' => $userId,
        ];

        if ($status !== null) {
            $filters['status'] = $status;
        }

        return $this->findAll($filters);
    }

    /**
     * Find todos by related element
     *
     * @return TodoItem[]
     */
    public function findByElement(int $elementId, string $elementType): array
    {
        return $this->findAll([
            'related_element_id' => $elementId,
            'related_element_type' => $elementType,
        ]);
    }

    /**
     * Find completed todos older than the given cutoff date
     *
     * @return TodoItem[]
     */
    public function findCompletedBefore(\DateTimeImmutable $cutoff): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from(Installer::TABLE_TODO_ITEMS)
            ->where('deleted_at IS NULL')
            ->andWhere('status = :completed')
            ->andWhere('completed_at < :cutoff')
            ->setParameter('completed', TodoStatus::Completed->value)
            ->setParameter('cutoff', $cutoff->format('Y-m-d H:i:s'));

        return array_map(fn ($row) => TodoItem::fromArray($row), $qb->executeQuery()->fetchAllAssociative());
    }

    /**
     * Find soft-deleted todos whose deletedAt is before the given cutoff date
     *
     * @return TodoItem[]
     */
    public function findSoftDeletedBefore(\DateTimeImmutable $cutoff): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from(Installer::TABLE_TODO_ITEMS)
            ->where('deleted_at IS NOT NULL')
            ->andWhere('deleted_at < :cutoff')
            ->setParameter('cutoff', $cutoff->format('Y-m-d H:i:s'));

        return array_map(fn ($row) => TodoItem::fromArray($row), $qb->executeQuery()->fetchAllAssociative());
    }

    /**
     * Batch soft-delete todos by IDs
     *
     * @param int[] $ids
     * @return int Number of records affected
     */
    public function batchSoftDelete(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->update(Installer::TABLE_TODO_ITEMS)
            ->set('deleted_at', ':deleted_at')
            ->where('id IN (:ids)')
            ->andWhere('deleted_at IS NULL')
            ->setParameter('deleted_at', (new \DateTimeImmutable())->format('Y-m-d H:i:s'))
            ->setParameter('ids', $ids, ArrayParameterType::INTEGER);

        return (int) $qb->executeStatement();
    }

    /**
     * Batch update a field for todos by IDs
     *
     * @param int[] $ids
     * @param array<string, mixed> $data
     * @return int Number of records affected
     */
    public function batchUpdate(array $ids, array $data): int
    {
        if (empty($ids)) {
            return 0;
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->update(Installer::TABLE_TODO_ITEMS)
            ->where('id IN (:ids)')
            ->setParameter('ids', $ids, ArrayParameterType::INTEGER);

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $qb->set('updated_at', ':updated_at')->setParameter('updated_at', $now);

        foreach (['status', 'priority', 'category', 'assigned_to_user_id', 'updated_by_user_id'] as $field) {
            if (array_key_exists($field, $data)) {
                $qb->set($field, ':' . $field)->setParameter($field, $data[$field]);
            }
        }

        return (int) $qb->executeStatement();
    }

    /**
     * Get statistics
     *
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select(
            'COUNT(*) as total',
            'SUM(CASE WHEN status = :open THEN 1 ELSE 0 END) as open',
            'SUM(CASE WHEN status = :in_progress THEN 1 ELSE 0 END) as in_progress',
            'SUM(CASE WHEN status = :completed THEN 1 ELSE 0 END) as completed',
            'SUM(CASE WHEN status = :cancelled THEN 1 ELSE 0 END) as cancelled',
            'SUM(CASE WHEN status = :on_hold THEN 1 ELSE 0 END) as on_hold',
            'SUM(CASE WHEN due_date < :now AND status NOT IN (:closed) THEN 1 ELSE 0 END) as overdue'
        )
            ->from(Installer::TABLE_TODO_ITEMS)
            ->where('deleted_at IS NULL')
            ->setParameter('open', TodoStatus::Open->value)
            ->setParameter('in_progress', TodoStatus::InProgress->value)
            ->setParameter('completed', TodoStatus::Completed->value)
            ->setParameter('cancelled', TodoStatus::Cancelled->value)
            ->setParameter('on_hold', TodoStatus::OnHold->value)
            ->setParameter('now', (new \DateTimeImmutable())->format('Y-m-d H:i:s'))
            ->setParameter('closed', [TodoStatus::Completed->value, TodoStatus::Cancelled->value], ArrayParameterType::STRING);

        return $qb->executeQuery()->fetchAssociative();
    }

    /**
     * Get statistics grouped by category using a SQL GROUP BY query
     *
     * @return array<int, array<string, mixed>>
     */
    public function getStatisticsByCategory(): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select(
            "COALESCE(category, 'uncategorized') as category",
            'COUNT(*) as count'
        )
            ->from(Installer::TABLE_TODO_ITEMS)
            ->where('deleted_at IS NULL')
            ->groupBy('category')
            ->orderBy('count', 'DESC');

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * Get statistics grouped by user
     *
     * @return array<int, array<string, mixed>>
     */
    public function getStatisticsByUser(): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select(
            'assigned_to_user_id',
            'COUNT(*) as total',
            'SUM(CASE WHEN status = :open THEN 1 ELSE 0 END) as open',
            'SUM(CASE WHEN status = :in_progress THEN 1 ELSE 0 END) as in_progress',
            'SUM(CASE WHEN status = :completed THEN 1 ELSE 0 END) as completed'
        )
            ->from(Installer::TABLE_TODO_ITEMS)
            ->where('deleted_at IS NULL')
            ->andWhere('assigned_to_user_id IS NOT NULL')
            ->groupBy('assigned_to_user_id')
            ->setParameter('open', TodoStatus::Open->value)
            ->setParameter('in_progress', TodoStatus::InProgress->value)
            ->setParameter('completed', TodoStatus::Completed->value);

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * Create a filtered query builder
     *
     * @param array<string, mixed> $filters
     */
    private function createFilteredQuery(array $filters): QueryBuilder
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')->from(Installer::TABLE_TODO_ITEMS);

        // Exclude soft-deleted by default
        if (!isset($filters['include_deleted']) || !$filters['include_deleted']) {
            $qb->andWhere('deleted_at IS NULL');
        }

        $this->applyStatusFilter($qb, $filters);
        $this->applyPriorityFilter($qb, $filters);
        $this->applyAssignedToFilter($qb, $filters);
        $this->applyCategoryFilter($qb, $filters);
        $this->applyRelatedElementFilter($qb, $filters);
        $this->applyDueDateFilter($qb, $filters);
        $this->applySearchFilter($qb, $filters);
        $this->applyOverdueFilter($qb, $filters);

        return $qb;
    }

    /** @param array<string, mixed> $filters */
    private function applyStatusFilter(QueryBuilder $qb, array $filters): void
    {
        if (isset($filters['status'])) {
            $qb->andWhere('status = :status')->setParameter('status', $filters['status']);
        }
    }

    /** @param array<string, mixed> $filters */
    private function applyPriorityFilter(QueryBuilder $qb, array $filters): void
    {
        if (isset($filters['priority'])) {
            $qb->andWhere('priority = :priority')->setParameter('priority', $filters['priority']);
        }
    }

    /** @param array<string, mixed> $filters */
    private function applyAssignedToFilter(QueryBuilder $qb, array $filters): void
    {
        if (isset($filters['assigned_to_user_id'])) {
            $qb->andWhere('assigned_to_user_id = :assigned_to')
                ->setParameter('assigned_to', $filters['assigned_to_user_id']);
        }
    }

    /** @param array<string, mixed> $filters */
    private function applyCategoryFilter(QueryBuilder $qb, array $filters): void
    {
        if (isset($filters['category'])) {
            $qb->andWhere('category = :category')->setParameter('category', $filters['category']);
        }
    }

    /** @param array<string, mixed> $filters */
    private function applyRelatedElementFilter(QueryBuilder $qb, array $filters): void
    {
        if (isset($filters['related_element_id'])) {
            $qb->andWhere('related_element_id = :element_id')
                ->setParameter('element_id', $filters['related_element_id']);
        }

        if (isset($filters['related_element_type'])) {
            $qb->andWhere('related_element_type = :element_type')
                ->setParameter('element_type', $filters['related_element_type']);
        }
    }

    /** @param array<string, mixed> $filters */
    private function applyDueDateFilter(QueryBuilder $qb, array $filters): void
    {
        if (isset($filters['due_before'])) {
            $qb->andWhere('due_date < :due_before')
                ->setParameter('due_before', $this->formatDateTime($filters['due_before']));
        }

        if (isset($filters['due_after'])) {
            $qb->andWhere('due_date > :due_after')
                ->setParameter('due_after', $this->formatDateTime($filters['due_after']));
        }
    }

    /** @param array<string, mixed> $filters */
    private function applySearchFilter(QueryBuilder $qb, array $filters): void
    {
        if (isset($filters['search'])) {
            $searchValue = substr((string) $filters['search'], 0, self::SEARCH_MAX_LENGTH);
            $qb->andWhere('(title LIKE :search OR description LIKE :search)')
                ->setParameter('search', '%' . $searchValue . '%');
        }
    }

    /** @param array<string, mixed> $filters */
    private function applyOverdueFilter(QueryBuilder $qb, array $filters): void
    {
        if (isset($filters['overdue']) && $filters['overdue']) {
            $qb->andWhere('due_date < :now')
                ->andWhere('status NOT IN (:closed_statuses)')
                ->setParameter('now', (new \DateTimeImmutable())->format('Y-m-d H:i:s'))
                ->setParameter('closed_statuses', [
                    TodoStatus::Completed->value,
                    TodoStatus::Cancelled->value,
                ], ArrayParameterType::STRING);
        }
    }

    /**
     * Format datetime for database storage
     */
    private function formatDateTime(mixed $date): ?string
    {
        if ($date === null) {
            return null;
        }

        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d H:i:s');
        }

        if (is_string($date)) {
            try {
                return (new \DateTimeImmutable($date))->format('Y-m-d H:i:s');
            } catch (\Exception) {
                return null;
            }
        }

        return null;
    }
}
