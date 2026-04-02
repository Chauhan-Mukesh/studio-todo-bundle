/**
 * Todo API Service
 *
 * Handles all API communication with the backend
 */

import axios, { AxiosInstance } from 'axios';
import type {
  TodoItem,
  TodoFilters,
  TodoCreateData,
  TodoUpdateData,
  ApiResponse,
  Statistics,
  UserStatistics,
  AuditEntry,
} from '../types';

class TodoApiService {
  private api: AxiosInstance;
  private baseUrl = '/pimcore-studio/api/studio-todo';

  constructor() {
    this.api = axios.create({
      baseURL: this.baseUrl,
      headers: {
        'Content-Type': 'application/json',
      },
    });
  }

  /**
   * Fetch todos with optional filters
   */
  async fetchTodos(
    filters: TodoFilters = {},
    page: number = 1,
    limit: number = 20
  ): Promise<ApiResponse<TodoItem[]>> {
    try {
      const params = {
        ...filters,
        page,
        limit,
      };

      const response = await this.api.get('/todos', { params });
      return response.data;
    } catch (error) {
      console.error('Error fetching todos:', error);
      throw error;
    }
  }

  /**
   * Fetch a single todo by ID
   */
  async fetchTodo(id: number): Promise<ApiResponse<TodoItem>> {
    try {
      const response = await this.api.get(`/todos/${id}`);
      return response.data;
    } catch (error) {
      console.error('Error fetching todo:', error);
      throw error;
    }
  }

  /**
   * Create a new todo
   */
  async createTodo(data: TodoCreateData): Promise<ApiResponse<TodoItem>> {
    try {
      const response = await this.api.post('/todos', data);
      return response.data;
    } catch (error) {
      console.error('Error creating todo:', error);
      throw error;
    }
  }

  /**
   * Update an existing todo
   */
  async updateTodo(id: number, data: Partial<TodoUpdateData>): Promise<ApiResponse<TodoItem>> {
    try {
      const response = await this.api.put(`/todos/${id}`, data);
      return response.data;
    } catch (error) {
      console.error('Error updating todo:', error);
      throw error;
    }
  }

  /**
   * Delete a todo (soft delete)
   */
  async deleteTodo(id: number): Promise<ApiResponse<void>> {
    try {
      const response = await this.api.delete(`/todos/${id}`);
      return response.data;
    } catch (error) {
      console.error('Error deleting todo:', error);
      throw error;
    }
  }

  /**
   * Complete a todo
   */
  async completeTodo(id: number): Promise<ApiResponse<TodoItem>> {
    try {
      const response = await this.api.post(`/todos/${id}/complete`);
      return response.data;
    } catch (error) {
      console.error('Error completing todo:', error);
      throw error;
    }
  }

  /**
   * Restore a deleted todo
   */
  async restoreTodo(id: number): Promise<ApiResponse<TodoItem>> {
    try {
      const response = await this.api.post(`/todos/${id}/restore`);
      return response.data;
    } catch (error) {
      console.error('Error restoring todo:', error);
      throw error;
    }
  }

  /**
   * Bulk update todos
   */
  async bulkUpdate(ids: number[], data: Partial<TodoUpdateData>): Promise<ApiResponse<{ updated: number }>> {
    try {
      const response = await this.api.post('/todos/bulk-update', { ids, data });
      return response.data;
    } catch (error) {
      console.error('Error bulk updating todos:', error);
      throw error;
    }
  }

  /**
   * Bulk delete todos
   */
  async bulkDelete(ids: number[]): Promise<ApiResponse<{ deleted: number }>> {
    try {
      const response = await this.api.post('/todos/bulk-delete', { ids });
      return response.data;
    } catch (error) {
      console.error('Error bulk deleting todos:', error);
      throw error;
    }
  }

  /**
   * Fetch statistics
   */
  async fetchStatistics(): Promise<ApiResponse<Statistics>> {
    try {
      const response = await this.api.get('/stats');
      return response.data;
    } catch (error) {
      console.error('Error fetching statistics:', error);
      throw error;
    }
  }

  /**
   * Fetch statistics by user
   */
  async fetchStatisticsByUser(): Promise<ApiResponse<UserStatistics[]>> {
    try {
      const response = await this.api.get('/stats/by-user');
      return response.data;
    } catch (error) {
      console.error('Error fetching user statistics:', error);
      throw error;
    }
  }

  /**
   * Fetch audit log for a todo
   */
  async fetchAuditLog(todoId: number, page: number = 1, limit: number = 50): Promise<ApiResponse<AuditEntry[]>> {
    try {
      const response = await this.api.get(`/audit/${todoId}`, {
        params: { page, limit },
      });
      return response.data;
    } catch (error) {
      console.error('Error fetching audit log:', error);
      throw error;
    }
  }
}

export default new TodoApiService();
