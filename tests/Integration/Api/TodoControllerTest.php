<?php

/**
 * Studio Todo Bundle for Pimcore 12+
 *
 * @license MIT
 * @author Mukesh Chauhan
 */

declare(strict_types=1);

namespace ChauhanMukesh\StudioTodoBundle\Tests\Integration\Api;

use ChauhanMukesh\StudioTodoBundle\Model\TodoItem;
use ChauhanMukesh\StudioTodoBundle\Enum\TodoStatus;
use ChauhanMukesh\StudioTodoBundle\Enum\TodoPriority;
use ChauhanMukesh\StudioTodoBundle\Repository\TodoRepository;
use ChauhanMukesh\StudioTodoBundle\Service\TodoManager;
use ChauhanMukesh\StudioTodoBundle\Service\AuditLogger;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Integration tests for Todo operations
 *
 * Tests TodoManager + TodoRepository interaction logic via mocks.
 * Full database integration tests require a running MySQL/MariaDB instance
 * and are covered in a CI environment with the TEST_DATABASE_URL env variable.
 */
class TodoControllerTest extends TestCase
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
                'defaults' => ['status' => 'open', 'priority' => 'medium'],
                'async' => ['enabled' => false],
            ]
        );
    }

    public function testCreateTodoReturnsId(): void
    {
        $this->repository->method('create')->willReturn(42);
        $this->repository->method('findById')->willReturn($this->createTodo(42));
        $this->auditLogger->method('logCreate');

        $id = $this->todoManager->create(['title' => 'Integration Test Todo'], 1);

        $this->assertSame(42, $id);
    }

    public function testCreateAppliesDefaultStatus(): void
    {
        $capturedData = null;

        $this->repository->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $data) use (&$capturedData) {
                $capturedData = $data;
                return true;
            }))
            ->willReturn(1);
        $this->repository->method('findById')->willReturn($this->createTodo(1));

        $this->todoManager->create(['title' => 'Test'], null);

        $this->assertSame('open', $capturedData['status'] ?? null);
    }

    public function testUpdateReturnsTrueOnSuccess(): void
    {
        $todo = $this->createTodo(1);
        $this->repository->method('findById')->willReturn($todo);
        $this->repository->method('update')->willReturn(true);

        $result = $this->todoManager->update(1, ['title' => 'Updated'], 1);

        $this->assertTrue($result);
    }

    public function testUpdateReturnsFalseWhenTodoNotFound(): void
    {
        $this->repository->method('findById')->willReturn(null);

        $result = $this->todoManager->update(999, ['title' => 'Updated'], 1);

        $this->assertFalse($result);
    }

    public function testSoftDeleteReturnsTrueOnSuccess(): void
    {
        $todo = $this->createTodo(1);
        $this->repository->method('findById')->willReturn($todo);
        $this->repository->method('softDelete')->willReturn(true);

        $result = $this->todoManager->softDelete(1, 1);

        $this->assertTrue($result);
    }

    public function testSoftDeleteReturnsFalseWhenNotFound(): void
    {
        $this->repository->method('findById')->willReturn(null);

        $result = $this->todoManager->softDelete(999, 1);

        $this->assertFalse($result);
    }

    public function testCompleteChangesStatusToCompleted(): void
    {
        $todo = $this->createTodo(1);
        $capturedData = null;

        $this->repository->expects($this->exactly(2))
            ->method('findById')
            ->willReturn($todo);

        $this->repository->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(function (array $data) use (&$capturedData) {
                $capturedData = $data;
                return true;
            }))
            ->willReturn(true);

        $result = $this->todoManager->complete(1, 1);

        $this->assertTrue($result);
        $this->assertSame('completed', $capturedData['status'] ?? null);
        $this->assertInstanceOf(\DateTimeImmutable::class, $capturedData['completed_at'] ?? null);
    }

    public function testCompleteReturnsFalseWhenNotFound(): void
    {
        $this->repository->method('findById')->willReturn(null);

        $result = $this->todoManager->complete(999, 1);

        $this->assertFalse($result);
    }

    public function testBulkDeleteDelegatesBatchCall(): void
    {
        $this->repository->expects($this->once())
            ->method('batchSoftDelete')
            ->with([1, 2, 3])
            ->willReturn(3);

        $count = $this->todoManager->bulkDelete([1, 2, 3], 1);

        $this->assertSame(3, $count);
    }

    public function testBulkDeleteReturnsZeroForEmptyIds(): void
    {
        $this->repository->expects($this->never())->method('batchSoftDelete');

        $count = $this->todoManager->bulkDelete([], 1);

        $this->assertSame(0, $count);
    }

    public function testBulkUpdateDelegatesBatchCall(): void
    {
        $this->repository->expects($this->once())
            ->method('batchUpdate')
            ->with([1, 2], $this->arrayHasKey('status'))
            ->willReturn(2);

        $count = $this->todoManager->bulkUpdate([1, 2], ['status' => 'completed'], 1);

        $this->assertSame(2, $count);
    }

    public function testCleanupCountsWithoutDeletingInDryRunMode(): void
    {
        $todos = [$this->createTodo(1), $this->createTodo(2)];

        $this->repository->expects($this->once())
            ->method('findCompletedBefore')
            ->willReturn($todos);

        $this->repository->expects($this->never())
            ->method('hardDelete');

        $count = $this->todoManager->cleanup(90, dryRun: true);

        $this->assertSame(2, $count);
    }

    public function testCleanupDeletesWhenNotDryRun(): void
    {
        $todos = [$this->createTodo(1), $this->createTodo(2)];

        $this->repository->method('findCompletedBefore')->willReturn($todos);
        $this->repository->method('findById')->willReturn($this->createTodo(1));
        $this->repository->method('hardDelete')->willReturn(true);
        $this->auditLogger->method('logCustom');

        $count = $this->todoManager->cleanup(90, dryRun: false);

        $this->assertSame(2, $count);
    }

    public function testFindByIdDelegatesToRepository(): void
    {
        $todo = $this->createTodo(5);
        $this->repository->method('findById')->with(5, false)->willReturn($todo);

        $result = $this->todoManager->findById(5);

        $this->assertSame($todo, $result);
    }

    public function testCountDelegatesToRepository(): void
    {
        $this->repository->method('count')->with(['status' => 'open'])->willReturn(7);

        $result = $this->todoManager->count(['status' => 'open']);

        $this->assertSame(7, $result);
    }

    private function createTodo(int $id): TodoItem
    {
        $now = new \DateTimeImmutable();
        return new TodoItem(
            id: $id,
            title: 'Test Todo #' . $id,
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
