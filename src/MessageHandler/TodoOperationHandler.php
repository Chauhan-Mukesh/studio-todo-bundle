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
            'operation' => $message->getOperation(),
            'todo_id' => $message->getTodoId(),
        ]);

        try {
            match ($message->getOperation()) {
                'created' => $this->handleCreated($message),
                'updated' => $this->handleUpdated($message),
                'deleted' => $this->handleDeleted($message),
                'restored' => $this->handleRestored($message),
                default => $this->logger?->warning('Unknown operation', [
                    'operation' => $message->getOperation(),
                ]),
            };

            $this->logger?->info('Async todo operation completed', [
                'operation' => $message->getOperation(),
                'todo_id' => $message->getTodoId(),
            ]);
        } catch (\Throwable $e) {
            $this->logger?->error('Error processing async todo operation', [
                'operation' => $message->getOperation(),
                'todo_id' => $message->getTodoId(),
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
        // Perform async actions after todo creation
        // For example: send notifications, update related elements, etc.

        $todo = $this->todoManager->findById($message->getTodoId());
        if (!$todo) {
            return;
        }

        // Send notification to assigned user
        if ($todo->assignedToUserId) {
            // TODO: Implement notification logic
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
        // Perform async actions after todo update
        $todo = $this->todoManager->findById($message->getTodoId());
        if (!$todo) {
            return;
        }

        // Check if user was assigned
        if (isset($message->getData()['assigned_to_user_id'])) {
            // TODO: Send assignment notification
            $this->logger?->info('Would send assignment notification', [
                'todo_id' => $todo->id,
                'user_id' => $todo->assignedToUserId,
            ]);
        }

        // Check if status changed
        if (isset($message->getData()['status'])) {
            // TODO: Trigger workflow transitions, send notifications
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
        // Perform async actions after todo deletion
        // For example: cleanup related data, send notifications, etc.

        $this->logger?->info('Todo deleted, performing cleanup', [
            'todo_id' => $message->getTodoId(),
        ]);

        // TODO: Implement cleanup logic
    }

    /**
     * Handle restored operation
     */
    private function handleRestored(TodoOperationMessage $message): void
    {
        // Perform async actions after todo restoration
        $todo = $this->todoManager->findById($message->getTodoId());
        if (!$todo) {
            return;
        }

        // TODO: Send restoration notification
        $this->logger?->info('Todo restored', [
            'todo_id' => $todo->id,
        ]);
    }
}
