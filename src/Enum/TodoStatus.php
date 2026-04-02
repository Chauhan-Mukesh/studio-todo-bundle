<?php

/**
 * Studio Todo Bundle for Pimcore 12+
 *
 * @license MIT
 * @author Mukesh Chauhan
 */

declare(strict_types=1);

namespace ChauhanMukesh\StudioTodoBundle\Enum;

/**
 * Todo status enum
 *
 * Represents the lifecycle states of a todo item
 */
enum TodoStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case OnHold = 'on_hold';

    /**
     * Get a user-friendly label for the status
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::OnHold => 'On Hold',
        };
    }

    /**
     * Check if the status represents an active todo
     */
    public function isActive(): bool
    {
        return $this === self::Open || $this === self::InProgress;
    }

    /**
     * Check if the status represents a closed todo
     */
    public function isClosed(): bool
    {
        return $this === self::Completed || $this === self::Cancelled;
    }
}
