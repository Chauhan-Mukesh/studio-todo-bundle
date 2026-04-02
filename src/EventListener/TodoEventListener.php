<?php

/**
 * Studio Todo Bundle for Pimcore 12+
 *
 * @license MIT
 * @author Mukesh Chauhan
 */

declare(strict_types=1);

namespace ChauhanMukesh\StudioTodoBundle\EventListener;

use ChauhanMukesh\StudioTodoBundle\Event\TodoEvent;
use ChauhanMukesh\StudioTodoBundle\Service\AuditLogger;
use Psr\Log\LoggerInterface;

/**
 * Todo Event Listener
 *
 * Listens to todo events and performs actions like logging, notifications, etc.
 */
class TodoEventListener
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Handle todo created event
     */
    public function onTodoCreated(TodoEvent $event): void
    {
        $todo = $event->getTodo();

        $this->logger?->info('Todo created', [
            'todo_id' => $todo->id,
            'title' => $todo->title,
            'status' => $todo->status->value,
            'priority' => $todo->priority->value,
        ]);

        // Additional custom logic can be added here
        // For example: sending notifications, updating related elements, etc.
    }

    /**
     * Handle todo updated event
     */
    public function onTodoUpdated(TodoEvent $event): void
    {
        $todo = $event->getTodo();

        $this->logger?->info('Todo updated', [
            'todo_id' => $todo->id,
            'title' => $todo->title,
        ]);

        // Check for specific field changes and trigger actions
        if ($event->hasFieldChanged('status')) {
            $this->onStatusChanged($event);
        }

        if ($event->hasFieldChanged('assigned_to_user_id')) {
            $this->onAssigned($event);
        }

        if ($event->hasFieldChanged('priority')) {
            $this->onPriorityChanged($event);
        }
    }

    /**
     * Handle todo deleted event
     */
    public function onTodoDeleted(TodoEvent $event): void
    {
        $todo = $event->getTodo();

        $this->logger?->info('Todo deleted', [
            'todo_id' => $todo->id,
            'title' => $todo->title,
        ]);

        // Additional cleanup or notification logic
    }

    /**
     * Handle todo completed event
     */
    public function onTodoCompleted(TodoEvent $event): void
    {
        $todo = $event->getTodo();

        $this->logger?->info('Todo completed', [
            'todo_id' => $todo->id,
            'title' => $todo->title,
            'completed_at' => $todo->completedAt?->format(\DateTimeInterface::ATOM),
        ]);

        // Send completion notifications, trigger workflows, etc.
    }

    /**
     * Handle todo restored event
     */
    public function onTodoRestored(TodoEvent $event): void
    {
        $todo = $event->getTodo();

        $this->logger?->info('Todo restored', [
            'todo_id' => $todo->id,
            'title' => $todo->title,
        ]);
    }

    /**
     * Handle status change
     */
    private function onStatusChanged(TodoEvent $event): void
    {
        $oldStatus = $event->getOldValue('status');
        $newStatus = $event->getNewValue('status');

        $this->logger?->info('Todo status changed', [
            'todo_id' => $event->getTodo()->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ]);

        // Send status change notifications
    }

    /**
     * Handle assignment change
     */
    private function onAssigned(TodoEvent $event): void
    {
        $oldUserId = $event->getOldValue('assigned_to_user_id');
        $newUserId = $event->getNewValue('assigned_to_user_id');

        $this->logger?->info('Todo assigned', [
            'todo_id' => $event->getTodo()->id,
            'old_user_id' => $oldUserId,
            'new_user_id' => $newUserId,
        ]);

        // Send assignment notifications to the new user
    }

    /**
     * Handle priority change
     */
    private function onPriorityChanged(TodoEvent $event): void
    {
        $oldPriority = $event->getOldValue('priority');
        $newPriority = $event->getNewValue('priority');

        $this->logger?->info('Todo priority changed', [
            'todo_id' => $event->getTodo()->id,
            'old_priority' => $oldPriority,
            'new_priority' => $newPriority,
        ]);

        // Send priority change notifications if needed
    }
}
