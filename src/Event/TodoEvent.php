<?php

/**
 * Studio Todo Bundle for Pimcore 12+
 *
 * @license MIT
 * @author Mukesh Chauhan
 */

declare(strict_types=1);

namespace ChauhanMukesh\StudioTodoBundle\Event;

use ChauhanMukesh\StudioTodoBundle\Model\TodoItem;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Todo Event - Base event for todo operations
 *
 * Contains the todo item and optionally the previous state
 */
class TodoEvent extends Event
{
    public function __construct(
        private readonly TodoItem $todo,
        private readonly ?TodoItem $previousTodo = null
    ) {
    }

    /**
     * Get the todo item
     */
    public function getTodo(): TodoItem
    {
        return $this->todo;
    }

    /**
     * Get the previous todo state (for update events)
     */
    public function getPreviousTodo(): ?TodoItem
    {
        return $this->previousTodo;
    }

    /**
     * Check if this is an update event with a previous state
     */
    public function hasPreviousState(): bool
    {
        return $this->previousTodo !== null;
    }

    /**
     * Check if a specific field changed (for update events)
     */
    public function hasFieldChanged(string $field): bool
    {
        if (!$this->hasPreviousState()) {
            return false;
        }

        $previousArray = $this->previousTodo->toArray();
        $currentArray = $this->todo->toArray();

        return ($previousArray[$field] ?? null) !== ($currentArray[$field] ?? null);
    }

    /**
     * Get the old value of a field (for update events)
     */
    public function getOldValue(string $field): mixed
    {
        if (!$this->hasPreviousState()) {
            return null;
        }

        $previousArray = $this->previousTodo->toArray();
        return $previousArray[$field] ?? null;
    }

    /**
     * Get the new value of a field
     */
    public function getNewValue(string $field): mixed
    {
        $currentArray = $this->todo->toArray();
        return $currentArray[$field] ?? null;
    }
}
