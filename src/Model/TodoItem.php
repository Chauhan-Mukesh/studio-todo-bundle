<?php

/**
 * Studio Todo Bundle for Pimcore 12+
 *
 * @license MIT
 * @author Mukesh Chauhan
 */

declare(strict_types=1);

namespace ChauhanMukesh\StudioTodoBundle\Model;

use ChauhanMukesh\StudioTodoBundle\Enum\TodoPriority;
use ChauhanMukesh\StudioTodoBundle\Enum\TodoStatus;

/**
 * Todo item value object
 *
 * Immutable representation of a todo item with all its properties
 */
readonly class TodoItem
{
    public function __construct(
        public int $id,
        public string $title,
        public ?string $description,
        public TodoStatus $status,
        public ?string $workflowState,
        public TodoPriority $priority,
        public ?int $relatedElementId,
        public ?string $relatedElementType,
        public ?string $relatedClass,
        public ?int $assignedToUserId,
        public ?int $createdByUserId,
        public ?int $updatedByUserId,
        public ?\DateTimeImmutable $dueDate,
        public ?\DateTimeImmutable $completedAt,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public ?\DateTimeImmutable $deletedAt,
        public int $position,
        public ?string $category,
        /** @var array<string, mixed>|null */
        public ?array $meta,
    ) {
    }

    /**
     * Check if the todo is overdue
     */
    public function isOverdue(): bool
    {
        if ($this->dueDate === null || $this->status->isClosed()) {
            return false;
        }

        return $this->dueDate < new \DateTimeImmutable();
    }

    /**
     * Check if the todo is soft-deleted
     */
    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /**
     * Convert to array for API responses
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'workflow_state' => $this->workflowState,
            'priority' => $this->priority->value,
            'related_element_id' => $this->relatedElementId,
            'related_element_type' => $this->relatedElementType,
            'related_class' => $this->relatedClass,
            'assigned_to_user_id' => $this->assignedToUserId,
            'created_by_user_id' => $this->createdByUserId,
            'updated_by_user_id' => $this->updatedByUserId,
            'due_date' => $this->dueDate?->format(\DateTimeInterface::ATOM),
            'completed_at' => $this->completedAt?->format(\DateTimeInterface::ATOM),
            'created_at' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updated_at' => $this->updatedAt->format(\DateTimeInterface::ATOM),
            'deleted_at' => $this->deletedAt?->format(\DateTimeInterface::ATOM),
            'position' => $this->position,
            'category' => $this->category,
            'meta' => $this->meta,
            'is_overdue' => $this->isOverdue(),
        ];
    }

    /**
     * Create from database row
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            title: (string) $data['title'],
            description: $data['description'] ?? null,
            status: TodoStatus::from($data['status']),
            workflowState: $data['workflow_state'] ?? null,
            priority: TodoPriority::from($data['priority']),
            relatedElementId: isset($data['related_element_id']) ? (int) $data['related_element_id'] : null,
            relatedElementType: $data['related_element_type'] ?? null,
            relatedClass: $data['related_class'] ?? null,
            assignedToUserId: isset($data['assigned_to_user_id']) ? (int) $data['assigned_to_user_id'] : null,
            createdByUserId: isset($data['created_by_user_id']) ? (int) $data['created_by_user_id'] : null,
            updatedByUserId: isset($data['updated_by_user_id']) ? (int) $data['updated_by_user_id'] : null,
            dueDate: isset($data['due_date']) ? self::parseDateSafely($data['due_date']) : null,
            completedAt: isset($data['completed_at']) ? self::parseDateSafely($data['completed_at']) : null,
            createdAt: self::parseDateSafely($data['created_at']) ?? new \DateTimeImmutable(),
            updatedAt: self::parseDateSafely($data['updated_at']) ?? new \DateTimeImmutable(),
            deletedAt: isset($data['deleted_at']) ? self::parseDateSafely($data['deleted_at']) : null,
            position: (int) ($data['position'] ?? 0),
            category: $data['category'] ?? null,
            meta: isset($data['meta']) ? json_decode($data['meta'], true) : null,
        );
    }

    private static function parseDateSafely(string $date): ?\DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($date);
        } catch (\Exception) {
            return null;
        }
    }
}
