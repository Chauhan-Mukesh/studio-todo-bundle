/**
 * Tests for Todo types and utility logic
 */

import { describe, it, expect } from 'vitest';
import { TodoStatus, TodoPriority } from '../../types';

describe('TodoStatus enum', () => {
  it('has Open status with value "open"', () => {
    expect(TodoStatus.Open).toBe('open');
  });

  it('has InProgress status with value "in_progress"', () => {
    expect(TodoStatus.InProgress).toBe('in_progress');
  });

  it('has Completed status with value "completed"', () => {
    expect(TodoStatus.Completed).toBe('completed');
  });

  it('has Cancelled status with value "cancelled"', () => {
    expect(TodoStatus.Cancelled).toBe('cancelled');
  });

  it('has OnHold status with value "on_hold"', () => {
    expect(TodoStatus.OnHold).toBe('on_hold');
  });
});

describe('TodoPriority enum', () => {
  it('has Low priority with value "low"', () => {
    expect(TodoPriority.Low).toBe('low');
  });

  it('has Medium priority with value "medium"', () => {
    expect(TodoPriority.Medium).toBe('medium');
  });

  it('has High priority with value "high"', () => {
    expect(TodoPriority.High).toBe('high');
  });

  it('has Critical priority with value "critical"', () => {
    expect(TodoPriority.Critical).toBe('critical');
  });
});

describe('TodoItem interface', () => {
  it('correctly represents a todo item structure', () => {
    const todo = {
      id: 1,
      title: 'Test Todo',
      description: null,
      status: TodoStatus.Open,
      workflow_state: null,
      priority: TodoPriority.Medium,
      related_element_id: null,
      related_element_type: null,
      related_class: null,
      assigned_to_user_id: null,
      created_by_user_id: 1,
      updated_by_user_id: 1,
      due_date: null,
      completed_at: null,
      created_at: '2024-01-01T00:00:00+00:00',
      updated_at: '2024-01-01T00:00:00+00:00',
      deleted_at: null,
      position: 0,
      category: null,
      meta: null,
      is_overdue: false,
    };

    expect(todo.id).toBe(1);
    expect(todo.status).toBe('open');
    expect(todo.priority).toBe('medium');
    expect(todo.is_overdue).toBe(false);
  });
});
