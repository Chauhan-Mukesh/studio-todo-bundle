/**
 * Main entry point for Studio Todo Bundle
 */

import React from 'react';
import ReactDOM from 'react-dom/client';
import Dashboard from './modules/todo-bundle/components/Dashboard';
import { ConfigProvider, App } from 'antd';

// Initialize the app
const initApp = () => {
  const rootElement = document.getElementById('studio-todo-root');

  if (rootElement) {
    const root = ReactDOM.createRoot(rootElement);
    root.render(
      <React.StrictMode>
        <ConfigProvider>
          <App>
            <Dashboard />
          </App>
        </ConfigProvider>
      </React.StrictMode>
    );
  }
};

// Wait for DOM to be ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initApp);
} else {
  initApp();
}

export default Dashboard;
