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
 * Todo permissions enum
 *
 * Defines the three-tier permission system:
 * - View: Read-only access to todos
 * - Manage: Create, update, delete todos
 * - Admin: Full access including settings
 */
enum TodoPermission: string
{
    case View = 'studio_todo_view';
    case Manage = 'studio_todo_manage';
    case Admin = 'studio_todo_admin';

    /** @var string Permission category displayed in Pimcore user management */
    public const CATEGORY = 'Studio Todo';
}
