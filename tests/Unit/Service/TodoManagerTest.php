<?php

/**
 * Studio Todo Bundle for Pimcore 12+
 *
 * @license MIT
 * @author Mukesh Chauhan
 */

declare(strict_types=1);

namespace ChauhanMukesh\StudioTodoBundle\Tests\Unit\Service;

use ChauhanMukesh\StudioTodoBundle\Enum\TodoPriority;
use ChauhanMukesh\StudioTodoBundle\Enum\TodoStatus;
use ChauhanMukesh\StudioTodoBundle\Model\TodoItem;
use ChauhanMukesh\StudioTodoBundle\Repository\TodoRepository;
use ChauhanMukesh\StudioTodoBundle\Service\AuditLogger;
use ChauhanMukesh\StudioTodoBundle\Service\TodoManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class TodoManagerTest extends TestCase
{
    private TodoRepository&MockObject $repository;
    private AuditLogger&MockObject $auditLogger;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private MessageBusInterface&MockObject $messageBus;
    private TodoManager $todoManager;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(TodoRepository::class);
        $this->auditLogger = $this->createMock(AuditLogger::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);

        $this->todoManager = new TodoManager(
            $this->repository,
            $this->auditLogger,
            $this->eventDispatcher,
            $this->messageBus,
            [
                'defaults' => [
                    'status' => 'open',
                    'priority' => 'medium',
                ],
                'async' => ['enabled' => false],
            ]
        );
    }

    public function testCreate(): void
    {
        $data = ['title' => 'Test Todo'];
        $userId = 5;
        $todoId = 1;

        $this->repository
            ->expects($this->once())
            ->method('create')
            ->willReturn($todoId);

        $this->repository
            ->expects($this->once())
            ->method('findById')
            ->with($todoId)
            ->willReturn($this->createMockTodo($todoId));

        $this->auditLogger
            ->expects($this->once())
            ->method('logCreate');

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch');

        $result = $this->todoManager->create($data, $userId);

        $this->assertSame($todoId, $result);
    }

    public function testUpdate(): void
    {
        $todoId = 1;
        $data = ['title' => 'Updated Title'];
        $userId = 5;

        $oldTodo = $this->createMockTodo($todoId);

        $this->repository
            ->expects($this->exactly(2))
            ->method('findById')
            ->willReturn($oldTodo);

        $this->repository
            ->expects($this->once())
            ->method('update')
            ->with($todoId, $this->anything())
            ->willReturn(true);

        $this->auditLogger
            ->expects($this->once())
            ->method('logUpdate');

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch');

        $result = $this->todoManager->update($todoId, $data, $userId);

        $this->assertTrue($result);
    }

    public function testSoftDelete(): void
    {
        $todoId = 1;
        $userId = 5;

        $todo = $this->createMockTodo($todoId);

        $this->repository
            ->expects($this->once())
            ->method('findById')
            ->with($todoId)
            ->willReturn($todo);

        $this->repository
            ->expects($this->once())
            ->method('softDelete')
            ->with($todoId)
            ->willReturn(true);

        $this->auditLogger
            ->expects($this->once())
            ->method('logDelete');

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch');

        $result = $this->todoManager->softDelete($todoId, $userId);

        $this->assertTrue($result);
    }

    public function testComplete(): void
    {
        $todoId = 1;
        $userId = 5;

        $this->repository
            ->expects($this->exactly(2))
            ->method('findById')
            ->willReturn($this->createMockTodo($todoId));

        $this->repository
            ->expects($this->once())
            ->method('update')
            ->willReturn(true);

        $this->auditLogger
            ->expects($this->once())
            ->method('logComplete');

        $result = $this->todoManager->complete($todoId, $userId);

        $this->assertTrue($result);
    }

    public function testFindById(): void
    {
        $todoId = 1;
        $expectedTodo = $this->createMockTodo($todoId);

        $this->repository
            ->expects($this->once())
            ->method('findById')
            ->with($todoId, false)
            ->willReturn($expectedTodo);

        $result = $this->todoManager->findById($todoId);

        $this->assertSame($expectedTodo, $result);
    }

    public function testFindAll(): void
    {
        $filters = ['status' => 'open'];
        $expectedTodos = [$this->createMockTodo(1), $this->createMockTodo(2)];

        $this->repository
            ->expects($this->once())
            ->method('findAll')
            ->with($filters, 100, 0)
            ->willReturn($expectedTodos);

        $result = $this->todoManager->findAll($filters);

        $this->assertSame($expectedTodos, $result);
    }

    public function testCount(): void
    {
        $filters = ['status' => 'open'];

        $this->repository
            ->expects($this->once())
            ->method('count')
            ->with($filters)
            ->willReturn(5);

        $result = $this->todoManager->count($filters);

        $this->assertSame(5, $result);
    }

    private function createMockTodo(int $id): TodoItem
    {
        $now = new \DateTimeImmutable();

        return new TodoItem(
            id: $id,
            title: 'Test Todo',
            description: null,
            status: TodoStatus::Open,
            workflowState: null,
            priority: TodoPriority::Medium,
            relatedElementId: null,
            relatedElementType: null,
            relatedClass: null,
            assignedToUserId: null,
            createdByUserId: 1,
            updatedByUserId: 1,
            dueDate: null,
            completedAt: null,
            createdAt: $now,
            updatedAt: $now,
            deletedAt: null,
            position: 0,
            category: null,
            meta: null
        );
    }
}
