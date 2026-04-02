<?php

/**
 * Studio Todo Bundle for Pimcore 12+
 *
 * @license MIT
 * @author Mukesh Chauhan
 */

declare(strict_types=1);

namespace ChauhanMukesh\StudioTodoBundle\Event;

/**
 * Todo Events - Constants for event names
 */
final class TodoEvents
{
    /**
     * Dispatched when a todo is created
     */
    public const string CREATED = 'studio_todo.created';

    /**
     * Dispatched when a todo is updated
     */
    public const string UPDATED = 'studio_todo.updated';

    /**
     * Dispatched when a todo is deleted (soft delete)
     */
    public const string DELETED = 'studio_todo.deleted';

    /**
     * Dispatched when a todo is completed
     */
    public const string COMPLETED = 'studio_todo.completed';

    /**
     * Dispatched when a todo is restored
     */
    public const string RESTORED = 'studio_todo.restored';

    /**
     * Dispatched when a todo is assigned to a user
     */
    public const string ASSIGNED = 'studio_todo.assigned';

    /**
     * Dispatched when a todo priority changes
     */
    public const string PRIORITY_CHANGED = 'studio_todo.priority_changed';

    /**
     * Dispatched when a todo status changes
     */
    public const string STATUS_CHANGED = 'studio_todo.status_changed';
}
