<?php

/**
 * Studio Todo Bundle for Pimcore 12+
 *
 * @license MIT
 * @author Mukesh Chauhan
 */

declare(strict_types=1);

namespace ChauhanMukesh\StudioTodoBundle\Tests\Unit\Model;

use ChauhanMukesh\StudioTodoBundle\Model\TodoItem;
use ChauhanMukesh\StudioTodoBundle\Enum\TodoStatus;
use ChauhanMukesh\StudioTodoBundle\Enum\TodoPriority;
use PHPUnit\Framework\TestCase;

class TodoItemTest extends TestCase
{
    public function testTodoItemCreation(): void
    {
        $now = new \DateTimeImmutable();

        $todo = new TodoItem(
            id: 1,
            title: 'Test Todo',
            description: 'Test Description',
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

        $this->assertSame(1, $todo->id);
        $this->assertSame('Test Todo', $todo->title);
        $this->assertSame('Test Description', $todo->description);
        $this->assertSame(TodoStatus::Open, $todo->status);
        $this->assertSame(TodoPriority::Medium, $todo->priority);
    }

    public function testIsOverdue(): void
    {
        $now = new \DateTimeImmutable();
        $past = new \DateTimeImmutable('-1 day');
        $future = new \DateTimeImmutable('+1 day');

        // Not overdue - no due date
        $todo1 = $this->createTodo(null, TodoStatus::Open);
        $this->assertFalse($todo1->isOverdue());

        // Overdue - due date in past
        $todo2 = $this->createTodo($past, TodoStatus::Open);
        $this->assertTrue($todo2->isOverdue());

        // Not overdue - due date in future
        $todo3 = $this->createTodo($future, TodoStatus::Open);
        $this->assertFalse($todo3->isOverdue());

        // Not overdue - completed
        $todo4 = $this->createTodo($past, TodoStatus::Completed);
        $this->assertFalse($todo4->isOverdue());
    }

    public function testIsDeleted(): void
    {
        $now = new \DateTimeImmutable();

        $todo1 = $this->createTodo(null, TodoStatus::Open, null);
        $this->assertFalse($todo1->isDeleted());

        $todo2 = $this->createTodo(null, TodoStatus::Open, $now);
        $this->assertTrue($todo2->isDeleted());
    }

    public function testToArray(): void
    {
        $now = new \DateTimeImmutable();
        $todo = $this->createTodo($now, TodoStatus::Open);

        $array = $todo->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('title', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('priority', $array);
        $this->assertArrayHasKey('is_overdue', $array);
        $this->assertSame('open', $array['status']);
        $this->assertSame('medium', $array['priority']);
    }

    public function testFromArray(): void
    {
        $data = [
            'id' => 1,
            'title' => 'Test Todo',
            'description' => 'Test Description',
            'status' => 'open',
            'workflow_state' => null,
            'priority' => 'medium',
            'related_element_id' => null,
            'related_element_type' => null,
            'related_class' => null,
            'assigned_to_user_id' => null,
            'created_by_user_id' => 1,
            'updated_by_user_id' => 1,
            'due_date' => null,
            'completed_at' => null,
            'created_at' => '2026-04-02 10:00:00',
            'updated_at' => '2026-04-02 10:00:00',
            'deleted_at' => null,
            'position' => 0,
            'category' => null,
            'meta' => null,
        ];

        $todo = TodoItem::fromArray($data);

        $this->assertInstanceOf(TodoItem::class, $todo);
        $this->assertSame(1, $todo->id);
        $this->assertSame('Test Todo', $todo->title);
        $this->assertSame(TodoStatus::Open, $todo->status);
        $this->assertSame(TodoPriority::Medium, $todo->priority);
    }

    private function createTodo(
        ?\DateTimeImmutable $dueDate,
        TodoStatus $status,
        ?\DateTimeImmutable $deletedAt = null
    ): TodoItem {
        $now = new \DateTimeImmutable();

        return new TodoItem(
            id: 1,
            title: 'Test Todo',
            description: null,
            status: $status,
            workflowState: null,
            priority: TodoPriority::Medium,
            relatedElementId: null,
            relatedElementType: null,
            relatedClass: null,
            assignedToUserId: null,
            createdByUserId: 1,
            updatedByUserId: 1,
            dueDate: $dueDate,
            completedAt: null,
            createdAt: $now,
            updatedAt: $now,
            deletedAt: $deletedAt,
            position: 0,
            category: null,
            meta: null
        );
    }
}
