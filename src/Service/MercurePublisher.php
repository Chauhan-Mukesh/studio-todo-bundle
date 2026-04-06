<?php

/**
 * Studio Todo Bundle for Pimcore 12+
 *
 * @license MIT
 * @author Mukesh Chauhan
 */

declare(strict_types=1);

namespace ChauhanMukesh\StudioTodoBundle\Service;

use ChauhanMukesh\StudioTodoBundle\Model\TodoItem;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Mercure Publisher Service
 *
 * Publishes real-time todo updates via Mercure SSE.
 * This service is optional and only active when symfony/mercure-bundle is installed.
 */
class MercurePublisher
{
    /** Mercure topic prefix for all studio-todo events */
    private const TOPIC_PREFIX = 'studio-todo/';

    public function __construct(
        private readonly ?HubInterface $hub = null
    ) {
    }

    /**
     * Publish a todo event to Mercure
     */
    public function publish(string $eventType, TodoItem $todo, ?TodoItem $previousTodo = null): void
    {
        if ($this->hub === null) {
            return;
        }

        $payload = [
            'event' => $eventType,
            'todo' => $todo->toArray(),
        ];

        if ($previousTodo !== null) {
            $payload['previous'] = $previousTodo->toArray();
        }

        $update = new Update(
            topics: [
                self::TOPIC_PREFIX . 'todos',
                self::TOPIC_PREFIX . 'todo/' . $todo->id,
            ],
            data: json_encode($payload),
            private: true
        );

        $this->hub->publish($update);
    }
}
