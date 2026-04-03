/**
 * Todo item type definitions
 *
 * Shared TypeScript types for the Studio Todo Bundle frontend.
 * These mirror the PHP-side enums and model properties.
 */

/** Lifecycle state of a todo item */
export enum TodoStatus {
  Open = 'open',
  InProgress = 'in_progress',
  Completed = 'completed',
  Cancelled = 'cancelled',
  OnHold = 'on_hold',
}

/** Urgency level of a todo item */
export enum TodoPriority {
  Low = 'low',
  Medium = 'medium',
  High = 'high',
  Critical = 'critical',
}

/** Full todo item as returned by the API */
export interface TodoItem {
  id: number;
  title: string;
  description: string | null;
  status: TodoStatus;
  /** Optional Pimcore workflow state identifier */
  workflow_state: string | null;
  priority: TodoPriority;
  /** ID of the linked Pimcore element, if any */
  related_element_id: number | null;
  /** Type of the linked element: 'object' | 'asset' | 'document' */
  related_element_type: string | null;
  /** Class name of the linked DataObject, if applicable */
  related_class: string | null;
  assigned_to_user_id: number | null;
  created_by_user_id: number | null;
  updated_by_user_id: number | null;
  /** ISO 8601 datetime string or null */
  due_date: string | null;
  /** ISO 8601 datetime string or null */
  completed_at: string | null;
  /** ISO 8601 datetime string */
  created_at: string;
  /** ISO 8601 datetime string */
  updated_at: string;
  /** ISO 8601 datetime string when soft-deleted, or null */
  deleted_at: string | null;
  /** Display order position within a list */
  position: number;
  category: string | null;
  /** Arbitrary JSON metadata for custom attributes */
  meta: Record<string, unknown> | null;
  /** True when due_date is in the past and the todo is not closed */
  is_overdue: boolean;
}

/** Query parameters accepted by the list endpoint */
export interface TodoFilters {
  status?: TodoStatus;
  priority?: TodoPriority;
  assigned_to_user_id?: number;
  category?: string;
  /** Full-text search across title and description */
  search?: string;
  /** When true, only overdue items are returned */
  overdue?: boolean;
  /** ISO 8601 upper bound for due_date */
  due_before?: string;
  /** ISO 8601 lower bound for due_date */
  due_after?: string;
}

/** Request body for creating a new todo */
export interface TodoCreateData {
  title: string;
  description?: string;
  status?: TodoStatus;
  priority?: TodoPriority;
  assigned_to_user_id?: number;
  /** ISO 8601 datetime string */
  due_date?: string;
  category?: string;
  related_element_id?: number;
  related_element_type?: string;
  meta?: Record<string, unknown>;
}

/** Request body for updating an existing todo (all fields except id are optional) */
export interface TodoUpdateData extends Partial<TodoCreateData> {
  id: number;
}

/** Pagination metadata included in list responses */
export interface PaginationData {
  total: number;
  page: number;
  limit: number;
  /** Total number of pages */
  pages: number;
}

/** Standard API envelope wrapping all responses */
export interface ApiResponse<T> {
  success: boolean;
  data?: T;
  error?: string;
  pagination?: PaginationData;
}

/** Aggregate statistics for all todo items */
export interface Statistics {
  total: number;
  open: number;
  in_progress: number;
  completed: number;
  cancelled: number;
  on_hold: number;
  overdue: number;
  /** Percentage of total that are open (0–100), present when total > 0 */
  open_percentage?: number;
  /** Percentage of total that are in progress (0–100), present when total > 0 */
  in_progress_percentage?: number;
  /** Percentage of total that are completed (0–100), present when total > 0 */
  completed_percentage?: number;
  /** Percentage of total that are overdue (0–100), present when total > 0 */
  overdue_percentage?: number;
}

/** A single entry in the audit log for a todo */
export interface AuditEntry {
  id: number;
  todo_id: number;
  /** Action type, e.g. create, update, delete, complete, restore */
  action: string;
  /** Name of the changed field, or null for lifecycle actions */
  field_name: string | null;
  /** Serialised previous value, or null */
  old_value: string | null;
  /** Serialised new value, or null */
  new_value: string | null;
  user_id: number | null;
  /** ISO 8601 datetime string */
  created_at: string;
}

/** Per-user todo statistics returned by the stats/by-user endpoint */
export interface UserStatistics {
  assigned_to_user_id: number;
  total: number;
  open: number;
  in_progress: number;
  completed: number;
}
