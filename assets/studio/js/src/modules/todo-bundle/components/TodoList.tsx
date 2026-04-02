/**
 * Todo List Component
 *
 * Main component for displaying and managing todos
 */

import React, { useState, useEffect } from 'react';
import {
  Table,
  Button,
  Space,
  Tag,
  Modal,
  message,
  Input,
  Select,
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
import type { TodoItem, TodoFilters, TodoStatus, TodoPriority } from '../types';

const { Search } = Input;
const { Option } = Select;

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

  // Fetch todos
  const fetchTodos = async () => {
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
          setPagination({
            current: response.pagination.page,
            pageSize: response.pagination.limit,
            total: response.pagination.total,
          });
        }
      }
    } catch (error) {
      message.error('Failed to fetch todos');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchTodos();
  }, [pagination.current, pagination.pageSize, filters]);

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
        <Tag color={getStatusColor(status)}>{status.replace('_', ' ').toUpperCase()}</Tag>
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
          <span style={{ color: record.is_overdue ? 'red' : undefined }}>
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
              // TODO: Open edit modal
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
            <Button type="primary" icon={<PlusOutlined />}>
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
            <Button danger onClick={() => {
              // TODO: Bulk delete
            }}>
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
            setPagination({
              current: newPagination.current || 1,
              pageSize: newPagination.pageSize || 20,
              total: pagination.total,
            });
          }}
        />
      </Space>
    </div>
  );
};

export default TodoList;
