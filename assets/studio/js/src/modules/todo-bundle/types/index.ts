/**
 * Todo item type definitions
 */

export enum TodoStatus {
  Open = 'open',
  InProgress = 'in_progress',
  Completed = 'completed',
  Cancelled = 'cancelled',
  OnHold = 'on_hold',
}

export enum TodoPriority {
  Low = 'low',
  Medium = 'medium',
  High = 'high',
  Critical = 'critical',
}

export interface TodoItem {
  id: number;
  title: string;
  description: string | null;
  status: TodoStatus;
  workflow_state: string | null;
  priority: TodoPriority;
  related_element_id: number | null;
  related_element_type: string | null;
  related_class: string | null;
  assigned_to_user_id: number | null;
  created_by_user_id: number | null;
  updated_by_user_id: number | null;
  due_date: string | null;
  completed_at: string | null;
  created_at: string;
  updated_at: string;
  deleted_at: string | null;
  position: number;
  category: string | null;
  meta: Record<string, unknown> | null;
  is_overdue: boolean;
}

export interface TodoFilters {
  status?: TodoStatus;
  priority?: TodoPriority;
  assigned_to_user_id?: number;
  category?: string;
  search?: string;
  overdue?: boolean;
  due_before?: string;
  due_after?: string;
}

export interface TodoCreateData {
  title: string;
  description?: string;
  status?: TodoStatus;
  priority?: TodoPriority;
  assigned_to_user_id?: number;
  due_date?: string;
  category?: string;
  related_element_id?: number;
  related_element_type?: string;
  meta?: Record<string, unknown>;
}

export interface TodoUpdateData extends Partial<TodoCreateData> {
  id: number;
}

export interface PaginationData {
  total: number;
  page: number;
  limit: number;
  pages: number;
}

export interface ApiResponse<T> {
  success: boolean;
  data?: T;
  error?: string;
  pagination?: PaginationData;
}

export interface Statistics {
  total: number;
  open: number;
  in_progress: number;
  completed: number;
  cancelled: number;
  on_hold: number;
  overdue: number;
  open_percentage?: number;
  in_progress_percentage?: number;
  completed_percentage?: number;
  overdue_percentage?: number;
}

export interface AuditEntry {
  id: number;
  todo_id: number;
  action: string;
  field_name: string | null;
  old_value: string | null;
  new_value: string | null;
  user_id: number | null;
  created_at: string;
}

export interface UserStatistics {
  assigned_to_user_id: number;
  total: number;
  open: number;
  in_progress: number;
  completed: number;
}
