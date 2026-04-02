/**
 * Todo List Component
 *
 * Main component for displaying and managing todos
 */

import React, { useState, useEffect, useCallback } from 'react';
import {
  Table,
  Button,
  Space,
  Tag,
  Modal,
  Form,
  Input,
  Select,
  DatePicker,
  message,
  Dropdown,
  MenuProps,
} from 'antd';
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
import type { TodoItem, TodoFilters, TodoStatus, TodoPriority, TodoUpdateData, TodoCreateData } from '../types';
import { useMercureSSE } from '../hooks/useMercureSSE';

const { Search } = Input;
const { Option } = Select;

const mercureHubUrl = (window as Window & { MERCURE_HUB_URL?: string }).MERCURE_HUB_URL ?? '/.well-known/mercure';

const TodoList: React.FC = () => {
  const [todos, setTodos] = useState<TodoItem[]>([]);
  const [loading, setLoading] = useState(false);
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

  // Fetch todos – stable reference via useCallback
  const fetchTodos = useCallback(async () => {
    setLoading(true);
    try {
      const response = await todoApi.fetchTodos(
        filters,
        pagination.current,
        pagination.pageSize
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
    } catch (error) {
      message.error('Failed to fetch todos');
    } finally {
      setLoading(false);
    }
  }, [filters, pagination.current, pagination.pageSize]);

  useEffect(() => {
    fetchTodos();
  }, [fetchTodos]);

  useMercureSSE({
    hubUrl: mercureHubUrl,
    topic: 'studio-todo/todos',
    onMessage: () => {
      fetchTodos();
    },
  });

  // Handle delete
  const handleDelete = async (id: number) => {
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
        } catch (error) {
          message.error('Failed to delete todo');
        }
      },
    });
  };

  // Handle complete
  const handleComplete = async (id: number) => {
    try {
      await todoApi.completeTodo(id);
      message.success('Todo completed successfully');
      fetchTodos();
    } catch (error) {
      message.error('Failed to complete todo');
    }
  };

  // Handle bulk delete
  const handleBulkDelete = () => {
    if (selectedRowKeys.length === 0) return;

    Modal.confirm({
      title: 'Bulk Delete',
      content: `Are you sure you want to delete ${selectedRowKeys.length} selected todos?`,
      okText: 'Delete',
      okType: 'danger',
      onOk: async () => {
        try {
          await todoApi.bulkDelete(selectedRowKeys as number[]);
          message.success(`Successfully deleted ${selectedRowKeys.length} todos`);
          setSelectedRowKeys([]);
          fetchTodos();
        } catch (error) {
          message.error('Failed to bulk delete todos');
        }
      },
    });
  };

  // Open edit modal
  const handleEdit = (todo: TodoItem) => {
    setEditingTodo(todo);
    editForm.setFieldsValue({
      title: todo.title,
      description: todo.description,
      status: todo.status,
      priority: todo.priority,
      category: todo.category,
    });
    setEditModalVisible(true);
  };

  // Submit edit modal
  const handleEditSubmit = async () => {
    if (!editingTodo) return;
    try {
      const values = await editForm.validateFields();
      await todoApi.updateTodo(editingTodo.id, values as Partial<TodoUpdateData>);
      message.success('Todo updated successfully');
      setEditModalVisible(false);
      setEditingTodo(null);
      editForm.resetFields();
      fetchTodos();
    } catch (error) {
      message.error('Failed to update todo');
    }
  };

  // Submit create modal
  const handleCreateSubmit = async () => {
    try {
      const values = await createForm.validateFields();
      await todoApi.createTodo(values as TodoCreateData);
      message.success('Todo created successfully');
      setCreateModalVisible(false);
      createForm.resetFields();
      fetchTodos();
    } catch (error) {
      message.error('Failed to create todo');
    }
  };

  // Get status color
  const getStatusColor = (status: TodoStatus): string => {
    const colors: Record<TodoStatus, string> = {
      open: 'blue',
      in_progress: 'orange',
      completed: 'green',
      cancelled: 'red',
      on_hold: 'gray',
    };
    return colors[status] || 'default';
  };

  // Get priority color
  const getPriorityColor = (priority: TodoPriority): string => {
    const colors: Record<TodoPriority, string> = {
      low: 'green',
      medium: 'blue',
      high: 'orange',
      critical: 'red',
    };
    return colors[priority] || 'default';
  };

  // Table columns
  const columns: ColumnsType<TodoItem> = [
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
      render: (category: string | null) => category || '-',
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
          <span style={{ color: record.is_overdue ? '#ff4d4f' : undefined }}>
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
            disabled: record.status === 'completed',
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
              disabled={record.status === 'completed'}
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
  ];

  // Row selection
  const rowSelection = {
    selectedRowKeys,
    onChange: (selectedKeys: React.Key[]) => {
      setSelectedRowKeys(selectedKeys);
    },
  };

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
            onSearch={(value) => setFilters({ ...filters, search: value })}
            allowClear
          />
          <Select
            placeholder="Status"
            style={{ width: 150 }}
            onChange={(value) => setFilters({ ...filters, status: value })}
            allowClear
          >
            <Option value="open">Open</Option>
            <Option value="in_progress">In Progress</Option>
            <Option value="completed">Completed</Option>
            <Option value="cancelled">Cancelled</Option>
            <Option value="on_hold">On Hold</Option>
          </Select>
          <Select
            placeholder="Priority"
            style={{ width: 150 }}
            onChange={(value) => setFilters({ ...filters, priority: value })}
            allowClear
          >
            <Option value="low">Low</Option>
            <Option value="medium">Medium</Option>
            <Option value="high">High</Option>
            <Option value="critical">Critical</Option>
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
              current: newPagination.current || 1,
              pageSize: newPagination.pageSize || 20,
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
            rules={[{ required: true, message: 'Title is required' }]}
          >
            <Input />
          </Form.Item>
          <Form.Item name="description" label="Description">
            <Input.TextArea rows={3} />
          </Form.Item>
          <Form.Item name="status" label="Status">
            <Select>
              <Option value="open">Open</Option>
              <Option value="in_progress">In Progress</Option>
              <Option value="completed">Completed</Option>
              <Option value="cancelled">Cancelled</Option>
              <Option value="on_hold">On Hold</Option>
            </Select>
          </Form.Item>
          <Form.Item name="priority" label="Priority">
            <Select>
              <Option value="low">Low</Option>
              <Option value="medium">Medium</Option>
              <Option value="high">High</Option>
              <Option value="critical">Critical</Option>
            </Select>
          </Form.Item>
          <Form.Item name="category" label="Category">
            <Input />
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
            rules={[{ required: true, message: 'Title is required' }]}
          >
            <Input />
          </Form.Item>
          <Form.Item name="description" label="Description">
            <Input.TextArea rows={3} />
          </Form.Item>
          <Form.Item name="status" label="Status" initialValue="open">
            <Select>
              <Option value="open">Open</Option>
              <Option value="in_progress">In Progress</Option>
            </Select>
          </Form.Item>
          <Form.Item name="priority" label="Priority" initialValue="medium">
            <Select>
              <Option value="low">Low</Option>
              <Option value="medium">Medium</Option>
              <Option value="high">High</Option>
              <Option value="critical">Critical</Option>
            </Select>
          </Form.Item>
          <Form.Item name="category" label="Category">
            <Input />
          </Form.Item>
        </Form>
      </Modal>
    </div>
  );
};

export default TodoList;
