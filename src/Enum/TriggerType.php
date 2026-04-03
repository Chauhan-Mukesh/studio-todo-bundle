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
    /** Action was triggered by a user through the UI or CLI */
    case Manual = 'manual';

    /** Action was triggered via a REST API call */
    case Api = 'api';

    /** Action was triggered by a Pimcore workflow transition */
    case Workflow = 'workflow';

    /** Action was triggered by a scheduled task or cron job */
    case Scheduled = 'scheduled';

    /** Action was triggered internally by the bundle itself */
    case System = 'system';
}
