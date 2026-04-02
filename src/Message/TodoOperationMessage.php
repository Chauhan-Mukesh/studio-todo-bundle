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
 * Message for async processing of todo operations
 */
class TodoOperationMessage
{
    public function __construct(
        public readonly string $operation,
        public readonly int $todoId,
        public readonly array $data = [],
        public readonly ?int $userId = null
    ) {
    }
}
