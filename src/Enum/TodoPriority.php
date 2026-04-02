<?php

/**
 * Studio Todo Bundle for Pimcore 12+
 *
 * @license MIT
 * @author Mukesh Chauhan
 * @package ChauhanMukesh\StudioTodoBundle\Enum
 */

declare(strict_types=1);

namespace ChauhanMukesh\StudioTodoBundle\Enum;

/**
 * Todo priority enum
 *
 * Defines priority levels for task management
 */
enum TodoPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    /**
     * Get a user-friendly label
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
            self::Critical => 'Critical',
        };
    }

    /**
     * Get numeric value for sorting (higher = more urgent)
     */
    public function getSortValue(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Medium => 2,
            self::High => 3,
            self::Critical => 4,
        };
    }
}
