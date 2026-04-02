<?php

/**
 * Studio Todo Bundle for Pimcore 12+
 *
 * @license MIT
 * @author Mukesh Chauhan
 */

declare(strict_types=1);

namespace ChauhanMukesh\StudioTodoBundle\Service;

use ChauhanMukesh\StudioTodoBundle\Repository\TodoRepository;
use ChauhanMukesh\StudioTodoBundle\Model\TodoItem;
use ChauhanMukesh\StudioTodoBundle\Enum\TodoStatus;
use ChauhanMukesh\StudioTodoBundle\Event\TodoEvent;
use ChauhanMukesh\StudioTodoBundle\Event\TodoEvents;
use ChauhanMukesh\StudioTodoBundle\Message\TodoOperationMessage;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Todo Manager Service
 *
 * Main business logic layer for todo operations
 */
class TodoManager
{
    private readonly bool $asyncEnabled;

    public function __construct(
        private readonly TodoRepository $repository,
        private readonly AuditLogger $auditLogger,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly MessageBusInterface $messageBus,
        private readonly array $config
    ) {
        $this->asyncEnabled = ($config['async']['enabled'] ?? false);
    }

    /**
     * Create a new todo
     */
    public function create(array $data, ?int $userId = null): int
    {
        // Apply defaults
        $data = $this->applyDefaults($data);
        $data['created_by_user_id'] = $userId;
        $data['updated_by_user_id'] = $userId;

        // Create todo
        $todoId = $this->repository->create($data);

        // Log audit
        $this->auditLogger->logCreate($todoId, $data, $userId);

        // Dispatch event
        $todo = $this->repository->findById($todoId);
        if ($todo) {
            $event = new TodoEvent($todo);
            $this->eventDispatcher->dispatch($event, TodoEvents::CREATED);
        }

        // Dispatch async message if enabled
        if ($this->isAsyncEnabled()) {
            $this->messageBus->dispatch(new TodoOperationMessage(
                operation: 'created',
                todoId: $todoId,
                data: $data
            ));
        }

        return $todoId;
    }

    /**
     * Update a todo
     */
    public function update(int $id, array $data, ?int $userId = null): bool
    {
        $todo = $this->repository->findById($id);
        if (!$todo) {
            return false;
        }

        $data['updated_by_user_id'] = $userId;

        // Store old data for audit
        $oldData = $todo->toArray();

        // Update todo
        $success = $this->repository->update($id, $data);

        if ($success) {
            // Log audit
            $this->auditLogger->logUpdate($id, $oldData, $data, $userId);

            // Dispatch event
            $updatedTodo = $this->repository->findById($id);
            if ($updatedTodo) {
                $event = new TodoEvent($updatedTodo, $todo);
                $this->eventDispatcher->dispatch($event, TodoEvents::UPDATED);

                // Dispatch specific field change events
                if ($event->hasFieldChanged('assigned_to_user_id')) {
                    $this->eventDispatcher->dispatch($event, TodoEvents::ASSIGNED);
                }
                if ($event->hasFieldChanged('priority')) {
                    $this->eventDispatcher->dispatch($event, TodoEvents::PRIORITY_CHANGED);
                }
                if ($event->hasFieldChanged('status')) {
                    $this->eventDispatcher->dispatch($event, TodoEvents::STATUS_CHANGED);
                }
            }

            // Dispatch async message if enabled
            if ($this->isAsyncEnabled()) {
                $this->messageBus->dispatch(new TodoOperationMessage(
                    operation: 'updated',
                    todoId: $id,
                    data: $data
                ));
            }
        }

        return $success;
    }

    /**
     * Soft delete a todo
     */
    public function softDelete(int $id, ?int $userId = null): bool
    {
        $todo = $this->repository->findById($id);
        if (!$todo) {
            return false;
        }

        $success = $this->repository->softDelete($id);

        if ($success) {
            // Log audit
            $this->auditLogger->logDelete($id, $userId);

            // Dispatch event
            $event = new TodoEvent($todo);
            $this->eventDispatcher->dispatch($event, TodoEvents::DELETED);

            // Dispatch async message if enabled
            if ($this->isAsyncEnabled()) {
                $this->messageBus->dispatch(new TodoOperationMessage(
                    operation: 'deleted',
                    todoId: $id
                ));
            }
        }

        return $success;
    }

    /**
     * Restore a soft-deleted todo
     */
    public function restore(int $id, ?int $userId = null): bool
    {
        $todo = $this->repository->findById($id, includeDeleted: true);
        if (!$todo || !$todo->isDeleted()) {
            return false;
        }

        $success = $this->repository->restore($id);

        if ($success) {
            // Log audit
            $this->auditLogger->logRestore($id, $userId);

            // Dispatch event
            $restoredTodo = $this->repository->findById($id);
            if ($restoredTodo) {
                $event = new TodoEvent($restoredTodo);
                $this->eventDispatcher->dispatch($event, TodoEvents::RESTORED);
            }

            // Dispatch async message if enabled
            if ($this->isAsyncEnabled()) {
                $this->messageBus->dispatch(new TodoOperationMessage(
                    operation: 'restored',
                    todoId: $id
                ));
            }
        }

        return $success;
    }

    /**
     * Hard delete a todo (permanent)
     */
    public function hardDelete(int $id, ?int $userId = null): bool
    {
        $todo = $this->repository->findById($id, includeDeleted: true);
        if (!$todo) {
            return false;
        }

        // Log audit before deletion
        $this->auditLogger->logCustom($id, 'hard_delete', userId: $userId);

        return $this->repository->hardDelete($id);
    }

    /**
     * Complete a todo
     */
    public function complete(int $id, ?int $userId = null): bool
    {
        $todo = $this->repository->findById($id);
        if (!$todo) {
            return false;
        }

        $data = [
            'status' => TodoStatus::Completed->value,
            'completed_at' => new \DateTimeImmutable(),
            'updated_by_user_id' => $userId,
        ];

        // Update directly in the repository to avoid dispatching the generic UPDATED event
        $success = $this->repository->update($id, $data);

        if ($success) {
            // Log audit
            $this->auditLogger->logComplete($id, $userId);

            // Dispatch only the COMPLETED event
            $completedTodo = $this->repository->findById($id);
            if ($completedTodo) {
                $event = new TodoEvent($completedTodo, $todo);
                $this->eventDispatcher->dispatch($event, TodoEvents::COMPLETED);
            }

            // Dispatch async message if enabled
            if ($this->isAsyncEnabled()) {
                $this->messageBus->dispatch(new TodoOperationMessage(
                    operation: 'completed',
                    todoId: $id
                ));
            }
        }

        return $success;
    }

    /**
     * Find todo by ID
     */
    public function findById(int $id, bool $includeDeleted = false): ?TodoItem
    {
        return $this->repository->findById($id, $includeDeleted);
    }

    /**
     * Find all todos with filters
     */
    public function findAll(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        return $this->repository->findAll($filters, $limit, $offset);
    }

    /**
     * Count todos with filters
     */
    public function count(array $filters = []): int
    {
        return $this->repository->count($filters);
    }

    /**
     * Find overdue todos
     */
    public function findOverdue(): array
    {
        return $this->repository->findOverdue();
    }

    /**
     * Find todos by user
     */
    public function findByUser(int $userId, ?string $status = null): array
    {
        return $this->repository->findByUser($userId, $status);
    }

    /**
     * Find todos by related element
     */
    public function findByElement(int $elementId, string $elementType): array
    {
        return $this->repository->findByElement($elementId, $elementType);
    }

    /**
     * Cleanup old completed todos
     */
    public function cleanup(int $days = 90, bool $dryRun = false): int
    {
        $cutoffDate = new \DateTimeImmutable("-{$days} days");
        $todos = $this->repository->findCompletedBefore($cutoffDate);

        if ($dryRun) {
            return count($todos);
        }

        $count = 0;
        foreach ($todos as $todo) {
            $this->hardDelete($todo->id);
            $count++;
        }

        return $count;
    }

    /**
     * Bulk update todos
     */
    public function bulkUpdate(array $ids, array $data, ?int $userId = null): int
    {
        if (empty($ids)) {
            return 0;
        }
        $data['updated_by_user_id'] = $userId;
        return $this->repository->batchUpdate($ids, $data);
    }

    /**
     * Bulk delete todos
     */
    public function bulkDelete(array $ids, ?int $userId = null): int
    {
        if (empty($ids)) {
            return 0;
        }
        return $this->repository->batchSoftDelete($ids);
    }

    /**
     * Apply default values from configuration
     */
    private function applyDefaults(array $data): array
    {
        $defaults = $this->config['defaults'] ?? [];

        if (!isset($data['status']) && isset($defaults['status'])) {
            $data['status'] = $defaults['status'];
        }

        if (!isset($data['priority']) && isset($defaults['priority'])) {
            $data['priority'] = $defaults['priority'];
        }

        if (!isset($data['category']) && isset($defaults['category'])) {
            $data['category'] = $defaults['category'];
        }

        return $data;
    }

    /**
     * Check if async processing is enabled
     */
    private function isAsyncEnabled(): bool
    {
        return $this->asyncEnabled;
    }
}
