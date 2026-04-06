/**
 * Todo List Component
 *
 * Main component for displaying and managing todos.  Renders a filterable,
 * sortable Ant Design table with inline row actions (complete, edit, delete)
 * and modals for creating or editing todos.  Listens to Mercure SSE for
 * real-time updates so the list stays in sync across browser tabs.
 */

import React, { useState, useEffect, useCallback, useMemo } from 'react';
import {
  Table,
  Button,
  Space,
  Tag,
  Modal,
  Form,
  Input,
  Select,
  message,
  Dropdown,
  theme,
} from 'antd';
import type { MenuProps } from 'antd';
import {
  PlusOutlined,
  DeleteOutlined,
  CheckOutlined,
  EditOutlined,
  MoreOutlined,
  ReloadOutlined,
} from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import todoApi from '../services/todoApi';
import type { TodoItem, TodoFilters, TodoUpdateData, TodoCreateData } from '../types';
import { TodoStatus, TodoPriority } from '../types';
import { useMercureSSE } from '../hooks/useMercureSSE';

const { Search } = Input;
const { Option } = Select;

const mercureHubUrl = (window as Window & { MERCURE_HUB_URL?: string }).MERCURE_HUB_URL ?? '/.well-known/mercure';

// Pure helpers – defined outside the component so they are never re-created
/** Return the Ant Design tag colour string for a given todo status */
const getStatusColor = (status: TodoStatus): string => {
  const colors: Record<TodoStatus, string> = {
    [TodoStatus.Open]: 'blue',
    [TodoStatus.InProgress]: 'orange',
    [TodoStatus.Completed]: 'green',
    [TodoStatus.Cancelled]: 'red',
    [TodoStatus.OnHold]: 'gray',
  };
  return colors[status] ?? 'default';
};

/** Return the Ant Design tag colour string for a given todo priority */
const getPriorityColor = (priority: TodoPriority): string => {
  const colors: Record<TodoPriority, string> = {
    [TodoPriority.Low]: 'green',
    [TodoPriority.Medium]: 'blue',
    [TodoPriority.High]: 'orange',
    [TodoPriority.Critical]: 'red',
  };
  return colors[priority] ?? 'default';
};

interface TodoListProps {
  /** Called after every successful mutation so the parent can refresh derived data (e.g. stats). */
  onMutation?: () => void;
}

const TodoList: React.FC<TodoListProps> = ({ onMutation }) => {
  const [todos, setTodos] = useState<TodoItem[]>([]);
  const [loading, setLoading] = useState(false);
  const { token } = theme.useToken();
  const [pagination, setPagination] = useState({
    current: 1,
    pageSize: 20,
    total: 0,
  });
  const [filters, setFilters] = useState<TodoFilters>({});
  const [selectedRowKeys, setSelectedRowKeys] = useState<React.Key[]>([]);
  const [editModalVisible, setEditModalVisible] = useState(false);
  const [editingTodo, setEditingTodo] = useState<TodoItem | null>(null);
  const [editForm] = Form.useForm();
  const [createModalVisible, setCreateModalVisible] = useState(false);
  const [createForm] = Form.useForm();

  // Destructure pagination values for stable useCallback dependencies
  const { current: currentPage, pageSize } = pagination;

  // Fetch todos – stable reference via useCallback
  const fetchTodos = useCallback(async () => {
    setLoading(true);
    try {
      const response = await todoApi.fetchTodos(
        filters,
        currentPage,
        pageSize
      );

      if (response.success && response.data) {
        setTodos(response.data);
        if (response.pagination) {
          setPagination((prev) => ({
            ...prev,
            current: response.pagination!.page,
            pageSize: response.pagination!.limit,
            total: response.pagination!.total,
          }));
        }
      }
    } catch {
      message.error('Failed to fetch todos');
    } finally {
      setLoading(false);
    }
  }, [filters, currentPage, pageSize]);

  useEffect(() => {
    fetchTodos();
  }, [fetchTodos]);

  // Update a filter key and reset to page 1 so results are never out of range
  const handleFilterChange = useCallback((update: Partial<TodoFilters>) => {
    setFilters((prev) => ({ ...prev, ...update }));
    setPagination((prev) => ({ ...prev, current: 1 }));
  }, []);

  const handleMercureMessage = useCallback(() => {
    fetchTodos();
  }, [fetchTodos]);

  useMercureSSE({
    hubUrl: mercureHubUrl,
    topic: 'studio-todo/todos',
    onMessage: handleMercureMessage,
  });

  // Handle delete
  const handleDelete = useCallback((id: number) => {
    Modal.confirm({
      title: 'Delete Todo',
      content: 'Are you sure you want to delete this todo?',
      okText: 'Delete',
      okType: 'danger',
      onOk: async () => {
        try {
          await todoApi.deleteTodo(id);
          message.success('Todo deleted successfully');
          fetchTodos();
          onMutation?.();
        } catch {
          message.error('Failed to delete todo');
        }
      },
    });
  }, [fetchTodos, onMutation]);

  // Handle complete
  const handleComplete = useCallback(async (id: number) => {
    try {
      await todoApi.completeTodo(id);
      message.success('Todo completed successfully');
      fetchTodos();
      onMutation?.();
    } catch {
      message.error('Failed to complete todo');
    }
  }, [fetchTodos, onMutation]);

  // Handle bulk delete
  const handleBulkDelete = useCallback(() => {
    if (selectedRowKeys.length === 0) return;

    Modal.confirm({
      title: 'Bulk Delete',
      content: `Are you sure you want to delete ${selectedRowKeys.length} selected todos?`,
      okText: 'Delete',
      okType: 'danger',
      onOk: async () => {
        try {
          await todoApi.bulkDelete(selectedRowKeys.map(Number));
          message.success(`Successfully deleted ${selectedRowKeys.length} todos`);
          setSelectedRowKeys([]);
          fetchTodos();
          onMutation?.();
        } catch {
          message.error('Failed to bulk delete todos');
        }
      },
    });
  }, [selectedRowKeys, fetchTodos, onMutation]);

  // Open edit modal
  const handleEdit = useCallback((todo: TodoItem) => {
    setEditingTodo(todo);
    editForm.setFieldsValue({
      title: todo.title,
      description: todo.description,
      status: todo.status,
      priority: todo.priority,
      category: todo.category,
    });
    setEditModalVisible(true);
  }, [editForm]);

  // Submit edit modal
  const handleEditSubmit = useCallback(async () => {
    if (!editingTodo) return;
    let values: Partial<TodoUpdateData>;
    try {
      values = await editForm.validateFields();
    } catch {
      // Form validation failed – inline errors are shown by Ant Design; nothing else to do
      return;
    }
    try {
      await todoApi.updateTodo(editingTodo.id, values);
      message.success('Todo updated successfully');
      setEditModalVisible(false);
      setEditingTodo(null);
      editForm.resetFields();
      fetchTodos();
      onMutation?.();
    } catch {
      message.error('Failed to update todo');
    }
  }, [editingTodo, editForm, fetchTodos, onMutation]);

  // Submit create modal
  const handleCreateSubmit = useCallback(async () => {
    let values: TodoCreateData;
    try {
      values = await createForm.validateFields();
    } catch {
      // Form validation failed – inline errors are shown by Ant Design; nothing else to do
      return;
    }
    try {
      await todoApi.createTodo(values);
      message.success('Todo created successfully');
      setCreateModalVisible(false);
      createForm.resetFields();
      fetchTodos();
      onMutation?.();
    } catch {
      message.error('Failed to create todo');
    }
  }, [createForm, fetchTodos, onMutation]);

  // Table columns – memoized so the table only re-renders when data-driving deps change
  const columns: ColumnsType<TodoItem> = useMemo(() => [
    {
      title: 'ID',
      dataIndex: 'id',
      key: 'id',
      width: 80,
    },
    {
      title: 'Title',
      dataIndex: 'title',
      key: 'title',
      ellipsis: true,
    },
    {
      title: 'Status',
      dataIndex: 'status',
      key: 'status',
      width: 120,
      render: (status: TodoStatus) => (
        <Tag color={getStatusColor(status)}>{status.replace(/_/g, ' ').toUpperCase()}</Tag>
      ),
    },
    {
      title: 'Priority',
      dataIndex: 'priority',
      key: 'priority',
      width: 100,
      render: (priority: TodoPriority) => (
        <Tag color={getPriorityColor(priority)}>{priority.toUpperCase()}</Tag>
      ),
    },
    {
      title: 'Category',
      dataIndex: 'category',
      key: 'category',
      width: 120,
      render: (category: string | null) => category ?? '-',
    },
    {
      title: 'Due Date',
      dataIndex: 'due_date',
      key: 'due_date',
      width: 120,
      render: (date: string | null, record: TodoItem) => {
        if (!date) return '-';
        const dueDate = new Date(date);
        return (
          <span style={{ color: record.is_overdue ? token.colorError : undefined }}>
            {dueDate.toLocaleDateString()}
          </span>
        );
      },
    },
    {
      title: 'Actions',
      key: 'actions',
      width: 150,
      render: (_, record: TodoItem) => {
        const items: MenuProps['items'] = [
          {
            key: 'edit',
            label: 'Edit',
            icon: <EditOutlined />,
          },
          {
            key: 'complete',
            label: 'Complete',
            icon: <CheckOutlined />,
            disabled: record.status === TodoStatus.Completed,
          },
          {
            type: 'divider',
          },
          {
            key: 'delete',
            label: 'Delete',
            icon: <DeleteOutlined />,
            danger: true,
          },
        ];

        const handleMenuClick: MenuProps['onClick'] = ({ key }) => {
          switch (key) {
            case 'edit':
              handleEdit(record);
              break;
            case 'complete':
              handleComplete(record.id);
              break;
            case 'delete':
              handleDelete(record.id);
              break;
          }
        };

        return (
          <Space>
            <Button
              size="small"
              icon={<CheckOutlined />}
              disabled={record.status === TodoStatus.Completed}
              onClick={() => handleComplete(record.id)}
            >
              Complete
            </Button>
            <Dropdown menu={{ items, onClick: handleMenuClick }} placement="bottomRight">
              <Button size="small" icon={<MoreOutlined />} />
            </Dropdown>
          </Space>
        );
      },
    },
  ], [token, handleComplete, handleDelete, handleEdit]);

  // Row selection – memoized to avoid recreating on unrelated renders
  const rowSelection = useMemo(() => ({
    selectedRowKeys,
    onChange: (selectedKeys: React.Key[]) => {
      setSelectedRowKeys(selectedKeys);
    },
  }), [selectedRowKeys]);

  return (
    <div style={{ padding: 24 }}>
      <Space direction="vertical" size="large" style={{ width: '100%' }}>
        {/* Header */}
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <h1>Todo Management</h1>
          <Space>
            <Button icon={<ReloadOutlined />} onClick={fetchTodos}>
              Refresh
            </Button>
            <Button type="primary" icon={<PlusOutlined />} onClick={() => setCreateModalVisible(true)}>
              Create Todo
            </Button>
          </Space>
        </div>

        {/* Filters */}
        <Space wrap>
          <Search
            placeholder="Search todos..."
            style={{ width: 300 }}
            onSearch={(value) => handleFilterChange({ search: value || undefined })}
            allowClear
          />
          <Select<TodoStatus>
            placeholder="Status"
            style={{ width: 150 }}
            onChange={(value) => handleFilterChange({ status: value })}
            allowClear
          >
            <Option value={TodoStatus.Open}>Open</Option>
            <Option value={TodoStatus.InProgress}>In Progress</Option>
            <Option value={TodoStatus.Completed}>Completed</Option>
            <Option value={TodoStatus.Cancelled}>Cancelled</Option>
            <Option value={TodoStatus.OnHold}>On Hold</Option>
          </Select>
          <Select<TodoPriority>
            placeholder="Priority"
            style={{ width: 150 }}
            onChange={(value) => handleFilterChange({ priority: value })}
            allowClear
          >
            <Option value={TodoPriority.Low}>Low</Option>
            <Option value={TodoPriority.Medium}>Medium</Option>
            <Option value={TodoPriority.High}>High</Option>
            <Option value={TodoPriority.Critical}>Critical</Option>
          </Select>
        </Space>

        {/* Bulk actions */}
        {selectedRowKeys.length > 0 && (
          <Space>
            <span>Selected {selectedRowKeys.length} items</span>
            <Button danger onClick={handleBulkDelete}>
              Bulk Delete
            </Button>
          </Space>
        )}

        {/* Table */}
        <Table
          columns={columns}
          dataSource={todos}
          rowKey="id"
          loading={loading}
          pagination={pagination}
          rowSelection={rowSelection}
          onChange={(newPagination) => {
            setPagination((prev) => ({
              ...prev,
              current: newPagination.current ?? 1,
              pageSize: newPagination.pageSize ?? 20,
            }));
          }}
        />
      </Space>

      {/* Edit Modal */}
      <Modal
        title="Edit Todo"
        open={editModalVisible}
        onOk={handleEditSubmit}
        onCancel={() => {
          setEditModalVisible(false);
          setEditingTodo(null);
          editForm.resetFields();
        }}
        okText="Save"
      >
        <Form form={editForm} layout="vertical">
          <Form.Item
            name="title"
            label="Title"
            rules={[{ required: true, message: 'Title is required' }, { max: 255, message: 'Title must be 255 characters or fewer' }]}
          >
            <Input maxLength={255} showCount />
          </Form.Item>
          <Form.Item name="description" label="Description">
            <Input.TextArea rows={3} maxLength={10000} showCount />
          </Form.Item>
          <Form.Item name="status" label="Status">
            <Select>
              <Option value={TodoStatus.Open}>Open</Option>
              <Option value={TodoStatus.InProgress}>In Progress</Option>
              <Option value={TodoStatus.Completed}>Completed</Option>
              <Option value={TodoStatus.Cancelled}>Cancelled</Option>
              <Option value={TodoStatus.OnHold}>On Hold</Option>
            </Select>
          </Form.Item>
          <Form.Item name="priority" label="Priority">
            <Select>
              <Option value={TodoPriority.Low}>Low</Option>
              <Option value={TodoPriority.Medium}>Medium</Option>
              <Option value={TodoPriority.High}>High</Option>
              <Option value={TodoPriority.Critical}>Critical</Option>
            </Select>
          </Form.Item>
          <Form.Item
            name="category"
            label="Category"
            rules={[{ max: 100, message: 'Category must be 100 characters or fewer' }]}
          >
            <Input maxLength={100} showCount />
          </Form.Item>
        </Form>
      </Modal>

      {/* Create Modal */}
      <Modal
        title="Create Todo"
        open={createModalVisible}
        onOk={handleCreateSubmit}
        onCancel={() => {
          setCreateModalVisible(false);
          createForm.resetFields();
        }}
        okText="Create"
      >
        <Form form={createForm} layout="vertical">
          <Form.Item
            name="title"
            label="Title"
            rules={[{ required: true, message: 'Title is required' }, { max: 255, message: 'Title must be 255 characters or fewer' }]}
          >
            <Input maxLength={255} showCount />
          </Form.Item>
          <Form.Item name="description" label="Description">
            <Input.TextArea rows={3} maxLength={10000} showCount />
          </Form.Item>
          <Form.Item name="status" label="Status" initialValue={TodoStatus.Open}>
            <Select>
              <Option value={TodoStatus.Open}>Open</Option>
              <Option value={TodoStatus.InProgress}>In Progress</Option>
            </Select>
          </Form.Item>
          <Form.Item name="priority" label="Priority" initialValue={TodoPriority.Medium}>
            <Select>
              <Option value={TodoPriority.Low}>Low</Option>
              <Option value={TodoPriority.Medium}>Medium</Option>
              <Option value={TodoPriority.High}>High</Option>
              <Option value={TodoPriority.Critical}>Critical</Option>
            </Select>
          </Form.Item>
          <Form.Item
            name="category"
            label="Category"
            rules={[{ max: 100, message: 'Category must be 100 characters or fewer' }]}
          >
            <Input maxLength={100} showCount />
          </Form.Item>
        </Form>
      </Modal>
    </div>
  );
};

export default TodoList;
