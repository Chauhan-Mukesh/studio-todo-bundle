<?php

/**
 * Studio Todo Bundle for Pimcore 12+
 *
 * @license MIT
 * @author Mukesh Chauhan
 */

declare(strict_types=1);

namespace ChauhanMukesh\StudioTodoBundle\Tests\Unit\Service;

use ChauhanMukesh\StudioTodoBundle\Service\AuditLogger;
use ChauhanMukesh\StudioTodoBundle\Repository\AuditRepository;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class AuditLoggerTest extends TestCase
{
    private AuditRepository&MockObject $repository;
    private AuditLogger $auditLogger;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AuditRepository::class);
        $this->auditLogger = new AuditLogger(
            $this->repository,
            ['enabled' => true, 'retention_days' => 365]
        );
    }

    public function testLogCreate(): void
    {
        $todoId = 1;
        $data = ['title' => 'Test Todo', 'status' => 'open'];
        $userId = 5;

        $this->repository
            ->expects($this->exactly(3)) // Main log + 2 fields
            ->method('log');

        $this->auditLogger->logCreate($todoId, $data, $userId);
    }

    public function testLogUpdate(): void
    {
        $todoId = 1;
        $oldData = ['title' => 'Old Title', 'status' => 'open'];
        $newData = ['title' => 'New Title', 'status' => 'in_progress'];
        $userId = 5;

        $this->repository
            ->expects($this->exactly(3)) // Main log + 2 changed fields
            ->method('log');

        $this->auditLogger->logUpdate($todoId, $oldData, $newData, $userId);
    }

    public function testLogDelete(): void
    {
        $todoId = 1;
        $userId = 5;

        $this->repository
            ->expects($this->once())
            ->method('log')
            ->with(
                $todoId,
                'delete',
                null,
                null,
                null,
                $userId
            );

        $this->auditLogger->logDelete($todoId, $userId);
    }

    public function testLogRestore(): void
    {
        $todoId = 1;
        $userId = 5;

        $this->repository
            ->expects($this->once())
            ->method('log')
            ->with(
                $todoId,
                'restore',
                null,
                null,
                null,
                $userId
            );

        $this->auditLogger->logRestore($todoId, $userId);
    }

    public function testLogComplete(): void
    {
        $todoId = 1;
        $userId = 5;

        $this->repository
            ->expects($this->once())
            ->method('log')
            ->with(
                $todoId,
                'complete',
                null,
                null,
                null,
                $userId
            );

        $this->auditLogger->logComplete($todoId, $userId);
    }

    public function testGetHistory(): void
    {
        $todoId = 1;
        $expectedHistory = [
            ['id' => 1, 'action' => 'create'],
            ['id' => 2, 'action' => 'update'],
        ];

        $this->repository
            ->expects($this->once())
            ->method('getByTodoId')
            ->with($todoId, 100, 0)
            ->willReturn($expectedHistory);

        $history = $this->auditLogger->getHistory($todoId);

        $this->assertSame($expectedHistory, $history);
    }

    public function testCleanup(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('deleteOlderThan')
            ->willReturn(50);

        $result = $this->auditLogger->cleanup();

        $this->assertSame(50, $result);
    }

    public function testLoggingDisabled(): void
    {
        $disabledLogger = new AuditLogger(
            $this->repository,
            ['enabled' => false]
        );

        $this->repository
            ->expects($this->never())
            ->method('log');

        $disabledLogger->logCreate(1, [], null);
        $disabledLogger->logUpdate(1, [], [], null);
        $disabledLogger->logDelete(1, null);
    }
}
