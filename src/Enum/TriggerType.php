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
 * Trigger type enum
 *
 * Identifies how a todo operation was triggered
 */
enum TriggerType: string
{
    case Manual = 'manual';
    case Api = 'api';
    case Workflow = 'workflow';
    case Scheduled = 'scheduled';
    case System = 'system';
}
