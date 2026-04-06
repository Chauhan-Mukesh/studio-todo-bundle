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
use ChauhanMukesh\StudioTodoBundle\Service\MercurePublisher;
use Psr\Log\LoggerInterface;

/**
 * Todo Event Listener
 *
 * Listens to todo events and performs actions like logging, notifications, etc.
 */
class TodoEventListener
{
    public function __construct(
        private readonly ?LoggerInterface $logger = null,
        private readonly ?MercurePublisher $mercurePublisher = null
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

        $this->mercurePublisher?->publish('created', $todo);
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

        $this->mercurePublisher?->publish('updated', $todo, $event->getPreviousTodo());
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

        $this->mercurePublisher?->publish('deleted', $todo);
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

        $this->mercurePublisher?->publish('completed', $todo, $event->getPreviousTodo());
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

        $this->mercurePublisher?->publish('restored', $todo);
    }

    /**
     * Handle todo assigned event
     */
    public function onTodoAssigned(TodoEvent $event): void
    {
        $todo = $event->getTodo();
        $this->logger?->info('Todo assigned', [
            'todo_id' => $todo->id,
            'old_user_id' => $event->getOldValue('assigned_to_user_id'),
            'new_user_id' => $event->getNewValue('assigned_to_user_id'),
        ]);
        $this->mercurePublisher?->publish('assigned', $todo, $event->getPreviousTodo());
    }

    /**
     * Handle todo priority changed event
     */
    public function onTodoPriorityChanged(TodoEvent $event): void
    {
        $todo = $event->getTodo();
        $this->logger?->info('Todo priority changed', [
            'todo_id' => $todo->id,
            'old_priority' => $event->getOldValue('priority'),
            'new_priority' => $event->getNewValue('priority'),
        ]);
        $this->mercurePublisher?->publish('priority_changed', $todo, $event->getPreviousTodo());
    }

    /**
     * Handle todo status changed event
     */
    public function onTodoStatusChanged(TodoEvent $event): void
    {
        $todo = $event->getTodo();
        $this->logger?->info('Todo status changed', [
            'todo_id' => $todo->id,
            'old_status' => $event->getOldValue('status'),
            'new_status' => $event->getNewValue('status'),
        ]);
        $this->mercurePublisher?->publish('status_changed', $todo, $event->getPreviousTodo());
    }

    /**
     * Handle workflow transition event
     */
    public function onWorkflowTransition(TodoEvent $event): void
    {
        $todo = $event->getTodo();
        $this->logger?->info('Todo workflow transition applied', [
            'todo_id'        => $todo->id,
            'old_state'      => $event->getOldValue('workflow_state'),
            'new_state'      => $event->getNewValue('workflow_state'),
            'new_status'     => $todo->status->value,
        ]);
        $this->mercurePublisher?->publish('workflow_transition', $todo, $event->getPreviousTodo());
    }
}
