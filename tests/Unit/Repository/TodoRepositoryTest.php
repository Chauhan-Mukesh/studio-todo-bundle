<?php

/**
 * Studio Todo Bundle for Pimcore 12+
 *
 * @license MIT
 * @author Mukesh Chauhan
 */

declare(strict_types=1);

namespace ChauhanMukesh\StudioTodoBundle\Tests\Unit\Repository;

use ChauhanMukesh\StudioTodoBundle\Model\TodoItem;
use ChauhanMukesh\StudioTodoBundle\Enum\TodoStatus;
use ChauhanMukesh\StudioTodoBundle\Enum\TodoPriority;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for TodoItem model and date parsing
 */
class TodoRepositoryTest extends TestCase
{
    public function testTodoItemFromArrayParsesValidDate(): void
    {
        $data = $this->getBaseData();

        $item = TodoItem::fromArray($data);

        $this->assertSame(1, $item->id);
        $this->assertSame('Test Todo', $item->title);
        $this->assertInstanceOf(\DateTimeImmutable::class, $item->createdAt);
    }

    public function testTodoItemFromArrayHandlesInvalidDateGracefully(): void
    {
        $data = $this->getBaseData(['due_date' => 'not-a-date']);

        // Should not throw; malformed date becomes null
        $item = TodoItem::fromArray($data);

        $this->assertNull($item->dueDate);
    }

    public function testTodoItemIsOverdueReturnsFalseWhenNoDueDate(): void
    {
        $item = TodoItem::fromArray($this->getBaseData());

        $this->assertFalse($item->isOverdue());
    }

    public function testTodoItemIsOverdueReturnsTrueForPastDueDate(): void
    {
        $item = TodoItem::fromArray($this->getBaseData([
            'due_date' => '2000-01-01 00:00:00',
        ]));

        $this->assertTrue($item->isOverdue());
    }

    public function testTodoItemIsOverdueFalseWhenCompleted(): void
    {
        $item = TodoItem::fromArray($this->getBaseData([
            'due_date' => '2000-01-01 00:00:00',
            'status' => 'completed',
        ]));

        $this->assertFalse($item->isOverdue());
    }

    public function testToArrayContainsAllExpectedKeys(): void
    {
        $item = TodoItem::fromArray($this->getBaseData());
        $array = $item->toArray();

        foreach (['id', 'title', 'status', 'priority', 'created_at', 'updated_at', 'is_overdue'] as $key) {
            $this->assertArrayHasKey($key, $array);
        }
    }

    public function testIsDeletedReturnsFalseByDefault(): void
    {
        $item = TodoItem::fromArray($this->getBaseData());

        $this->assertFalse($item->isDeleted());
    }

    public function testIsDeletedReturnsTrueWhenDeletedAtSet(): void
    {
        $item = TodoItem::fromArray($this->getBaseData(['deleted_at' => '2024-01-01 00:00:00']));

        $this->assertTrue($item->isDeleted());
    }

    private function getBaseData(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'title' => 'Test Todo',
            'description' => null,
            'status' => 'open',
            'workflow_state' => null,
            'priority' => 'medium',
            'related_element_id' => null,
            'related_element_type' => null,
            'related_class' => null,
            'assigned_to_user_id' => null,
            'created_by_user_id' => null,
            'updated_by_user_id' => null,
            'due_date' => null,
            'completed_at' => null,
            'created_at' => '2024-01-01 12:00:00',
            'updated_at' => '2024-01-01 12:00:00',
            'deleted_at' => null,
            'position' => 0,
            'category' => null,
            'meta' => null,
        ], $overrides);
    }
}
