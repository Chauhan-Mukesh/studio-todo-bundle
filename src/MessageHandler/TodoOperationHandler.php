<?php

/**
 * Studio Todo Bundle for Pimcore 12+
 *
 * @license MIT
 * @author Mukesh Chauhan
 */

declare(strict_types=1);

namespace ChauhanMukesh\StudioTodoBundle\MessageHandler;

use ChauhanMukesh\StudioTodoBundle\Message\TodoOperationMessage;
use ChauhanMukesh\StudioTodoBundle\Service\TodoManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Todo Operation Handler
 *
 * Handles async processing of todo operations
 */
#[AsMessageHandler]
class TodoOperationHandler
{
    public function __construct(
        private readonly TodoManager $todoManager,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Handle the message
     */
    public function __invoke(TodoOperationMessage $message): void
    {
        $this->logger?->info('Processing async todo operation', [
            'operation' => $message->operation,
            'todo_id' => $message->todoId,
        ]);

        try {
            match ($message->operation) {
                'created' => $this->handleCreated($message),
                'updated' => $this->handleUpdated($message),
                'deleted' => $this->handleDeleted($message),
                'restored' => $this->handleRestored($message),
                default => $this->logger?->warning('Unknown operation', [
                    'operation' => $message->operation,
                ]),
            };

            $this->logger?->info('Async todo operation completed', [
                'operation' => $message->operation,
                'todo_id' => $message->todoId,
            ]);
        } catch (\Throwable $e) {
            $this->logger?->error('Error processing async todo operation', [
                'operation' => $message->operation,
                'todo_id' => $message->todoId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle created operation
     */
    private function handleCreated(TodoOperationMessage $message): void
    {
        $todo = $this->todoManager->findById($message->todoId);
        if (!$todo) {
            return;
        }

        if ($todo->assignedToUserId) {
            $this->logger?->info('Would send creation notification', [
                'todo_id' => $todo->id,
                'user_id' => $todo->assignedToUserId,
            ]);
        }
    }

    /**
     * Handle updated operation
     */
    private function handleUpdated(TodoOperationMessage $message): void
    {
        $todo = $this->todoManager->findById($message->todoId);
        if (!$todo) {
            return;
        }

        if (isset($message->data['assigned_to_user_id'])) {
            $this->logger?->info('Would send assignment notification', [
                'todo_id' => $todo->id,
                'user_id' => $todo->assignedToUserId,
            ]);
        }

        if (isset($message->data['status'])) {
            $this->logger?->info('Status changed, would trigger workflows', [
                'todo_id' => $todo->id,
                'new_status' => $todo->status->value,
            ]);
        }
    }

    /**
     * Handle deleted operation
     */
    private function handleDeleted(TodoOperationMessage $message): void
    {
        $this->logger?->info('Todo deleted, performing cleanup', [
            'todo_id' => $message->todoId,
        ]);
    }

    /**
     * Handle restored operation
     */
    private function handleRestored(TodoOperationMessage $message): void
    {
        $todo = $this->todoManager->findById($message->todoId);
        if (!$todo) {
            return;
        }

        $this->logger?->info('Todo restored', [
            'todo_id' => $todo->id,
        ]);
    }
}
