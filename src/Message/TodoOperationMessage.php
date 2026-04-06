<?php

/**
 * Studio Todo Bundle for Pimcore 12+
 *
 * @license MIT
 * @author Mukesh Chauhan
 */

declare(strict_types=1);

namespace ChauhanMukesh\StudioTodoBundle\Message;

/**
 * Todo Operation Message
 *
 * Message for async processing of todo operations via Symfony Messenger.
 * Dispatched by TodoManager and consumed by TodoOperationHandler.
 */
class TodoOperationMessage
{
    /**
     * @param string               $operation The operation type: created, updated, deleted, or restored
     * @param int                  $todoId    The ID of the todo item being processed
     * @param array<string, mixed> $data      Optional additional data for the operation
     * @param int|null             $userId    The ID of the user who triggered the operation
     */
    public function __construct(
        public readonly string $operation,
        public readonly int $todoId,
        public readonly array $data = [],
        public readonly ?int $userId = null
    ) {
    }
}
