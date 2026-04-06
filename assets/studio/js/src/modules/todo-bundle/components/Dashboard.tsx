/**
 * Todo Dashboard Component
 *
 * Top-level view that renders summary statistics cards (total, open, completed,
 * overdue) and the full TodoList below them.  Statistics are fetched on mount
 * and can be refreshed by child components via the `onMutation` callback passed
 * to TodoList.
 */

import React, { useState, useEffect, useCallback } from 'react';
import { Card, Row, Col, Statistic, Spin, message, theme } from 'antd';
import {
  CheckCircleOutlined,
  ClockCircleOutlined,
  ExclamationCircleOutlined,
  FileTextOutlined,
} from '@ant-design/icons';
import todoApi from '../services/todoApi';
import TodoList from './TodoList';
import type { Statistics } from '../types';

const Dashboard: React.FC = () => {
  const [stats, setStats] = useState<Statistics | null>(null);
  const [loading, setLoading] = useState(true);
  const { token } = theme.useToken();

  /** Fetch and update the statistics state; shows an error message on failure */
  const fetchStatistics = useCallback(async () => {
    try {
      const response = await todoApi.fetchStatistics();
      if (response.success && response.data) {
        setStats(response.data);
      }
    } catch {
      message.error('Failed to fetch statistics');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    let mounted = true;

    todoApi.fetchStatistics()
      .then((response) => {
        if (mounted && response.success && response.data) {
          setStats(response.data);
        }
      })
      .catch(() => {
        if (mounted) {
          message.error('Failed to fetch statistics');
        }
      })
      .finally(() => {
        if (mounted) {
          setLoading(false);
        }
      });

    return () => { mounted = false; };
  }, []);

  if (loading) {
    return (
      <div style={{ textAlign: 'center', padding: '100px' }}>
        <Spin size="large" />
      </div>
    );
  }

  return (
    <div style={{ padding: 24 }}>
      {/* Statistics Cards */}
      {stats && (
        <Row gutter={16} style={{ marginBottom: 24 }}>
          <Col span={6}>
            <Card>
              <Statistic
                title="Total Todos"
                value={stats.total}
                prefix={<FileTextOutlined />}
              />
            </Card>
          </Col>
          <Col span={6}>
            <Card>
              <Statistic
                title="Open"
                value={stats.open}
                valueStyle={{ color: token.colorInfo }}
                prefix={<ClockCircleOutlined />}
              />
            </Card>
          </Col>
          <Col span={6}>
            <Card>
              <Statistic
                title="Completed"
                value={stats.completed}
                valueStyle={{ color: token.colorSuccess }}
                prefix={<CheckCircleOutlined />}
              />
            </Card>
          </Col>
          <Col span={6}>
            <Card>
              <Statistic
                title="Overdue"
                value={stats.overdue}
                valueStyle={{ color: token.colorError }}
                prefix={<ExclamationCircleOutlined />}
              />
            </Card>
          </Col>
        </Row>
      )}

      {/* Todo List */}
      <TodoList onMutation={fetchStatistics} />
    </div>
  );
};

export default Dashboard;
