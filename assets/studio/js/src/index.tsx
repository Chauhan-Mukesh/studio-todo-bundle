/**
 * Main entry point for Studio Todo Bundle
 *
 * Bootstraps the React application inside the `#studio-todo-root` element
 * that Pimcore Studio injects into the page when the Todo widget is rendered.
 */

import React from 'react';
import ReactDOM from 'react-dom/client';
import Dashboard from './modules/todo-bundle/components/Dashboard';
import { ConfigProvider, App } from 'antd';

/**
 * Mount the React app into the `#studio-todo-root` DOM node.
 * Safe to call before or after the DOMContentLoaded event.
 */
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
