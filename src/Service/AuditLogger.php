<?php

/**
 * Studio Todo Bundle for Pimcore 12+
 *
 * @license MIT
 * @author Mukesh Chauhan
 */

declare(strict_types=1);

namespace ChauhanMukesh\StudioTodoBundle\Service;

use ChauhanMukesh\StudioTodoBundle\Repository\AuditRepository;

/**
 * Audit Logger Service
 *
 * Handles audit logging for all todo operations
 */
class AuditLogger
{
    public function __construct(
        private readonly AuditRepository $repository,
        private readonly array $config
    ) {
    }

    /**
     * Log a create action
     */
    public function logCreate(int $todoId, array $data, ?int $userId = null): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $this->repository->log(
            todoId: $todoId,
            action: 'create',
            userId: $userId
        );

        // Log initial field values
        foreach ($data as $field => $value) {
            if ($this->shouldLogField($field)) {
                $this->repository->log(
                    todoId: $todoId,
                    action: 'set',
                    fieldName: $field,
                    oldValue: null,
                    newValue: $value,
                    userId: $userId
                );
            }
        }
    }

    /**
     * Log an update action
     */
    public function logUpdate(int $todoId, array $oldData, array $newData, ?int $userId = null): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $this->repository->log(
            todoId: $todoId,
            action: 'update',
            userId: $userId
        );

        // Log field changes
        foreach ($newData as $field => $newValue) {
            $oldValue = $oldData[$field] ?? null;

            if ($oldValue !== $newValue && $this->shouldLogField($field)) {
                $this->repository->log(
                    todoId: $todoId,
                    action: 'change',
                    fieldName: $field,
                    oldValue: $oldValue,
                    newValue: $newValue,
                    userId: $userId
                );
            }
        }
    }

    /**
     * Log a delete action
     */
    public function logDelete(int $todoId, ?int $userId = null): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $this->repository->log(
            todoId: $todoId,
            action: 'delete',
            userId: $userId
        );
    }

    /**
     * Log a restore action
     */
    public function logRestore(int $todoId, ?int $userId = null): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $this->repository->log(
            todoId: $todoId,
            action: 'restore',
            userId: $userId
        );
    }

    /**
     * Log a complete action
     */
    public function logComplete(int $todoId, ?int $userId = null): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $this->repository->log(
            todoId: $todoId,
            action: 'complete',
            userId: $userId
        );
    }

    /**
     * Log a custom action
     */
    public function logCustom(
        int $todoId,
        string $action,
        ?string $fieldName = null,
        mixed $oldValue = null,
        mixed $newValue = null,
        ?int $userId = null
    ): void {
        if (!$this->isEnabled()) {
            return;
        }

        $this->repository->log(
            todoId: $todoId,
            action: $action,
            fieldName: $fieldName,
            oldValue: $oldValue,
            newValue: $newValue,
            userId: $userId
        );
    }

    /**
     * Get audit history for a todo
     */
    public function getHistory(int $todoId, int $limit = 100, int $offset = 0): array
    {
        return $this->repository->getByTodoId($todoId, $limit, $offset);
    }

    /**
     * Clean up old audit logs
     */
    public function cleanup(): int
    {
        if (!isset($this->config['retention_days'])) {
            return 0;
        }

        $retentionDays = (int) $this->config['retention_days'];
        $cutoffDate = new \DateTimeImmutable("-{$retentionDays} days");

        return $this->repository->deleteOlderThan($cutoffDate);
    }

    /**
     * Check if audit logging is enabled
     */
    private function isEnabled(): bool
    {
        return $this->config['enabled'] ?? true;
    }

    /**
     * Check if a field should be logged
     */
    private function shouldLogField(string $field): bool
    {
        // Don't log internal fields
        $excludedFields = ['updated_at', 'created_at'];

        return !in_array($field, $excludedFields, true);
    }
}
