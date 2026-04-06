# Studio Todo Bundle for Pimcore 12+

A production-grade, feature-rich todo/task management bundle for Pimcore 11, 12, and newer versions with full Pimcore Studio UI integration, real-time updates, workflow support, and comprehensive security.

## 🚀 Features

### Core Functionality
- ✅ **Complete Task Management**: Create, update, delete, and manage todos with full CRUD operations
- ✅ **Status Tracking**: Open, In Progress, Completed, Cancelled, On Hold states
- ✅ **Priority Levels**: Low, Medium, High, Critical with smart sorting
- ✅ **User Assignment**: Assign todos to specific Pimcore users
- ✅ **Element Relations**: Link todos to any Pimcore element (DataObjects, Assets, Documents)
- ✅ **Categories & Organization**: Group todos by category with drag-drop positioning
- ✅ **Due Dates & Reminders**: Track deadlines with overdue detection
- ✅ **Soft Deletes**: Safe deletion with recovery capability

### Advanced Features
- ✅ **Audit Trail**: Complete change history with field-level tracking
- ✅ **Async Processing**: Message queue integration for performance
- ✅ **Security**: Three-tier permission system (View, Manage, Admin)
- ✅ **Flexible Metadata**: JSON meta field for custom attributes
- ✅ **Real-time Updates**: Live synchronization via Mercure SSE (`MercurePublisher` + `useMercureSSE` hook; requires `symfony/mercure-bundle`)
- ✅ **Multi-language**: i18n support for EN, DE, FR (YAML translation files in `src/Resources/translations/`)
- ⬜ **Workflow Integration**: Pimcore workflow state management (configuration exists, implementation pending)

### Technical Excellence
- ✅ **Pimcore 11 & 12 Compatible**: Works with both major versions
- ✅ **Symfony 6 & 7 Compatible**: Modern Symfony best practices
- ✅ **PHP 8.2+**: Leverages modern PHP features (enums, readonly properties, typed constants)
- ✅ **React 18 + TypeScript**: Type-safe Studio UI integration with live updates
- ✅ **RESTful API**: Clean, well-documented API endpoints (including hard-delete endpoint)
- ✅ **CLI Commands**: Powerful command-line tools
- ✅ **Comprehensive Tests**: Unit and integration test coverage (PHPUnit + Vitest)
- ✅ **Full Documentation**: Inline docs, README, and API documentation

## 📋 Requirements

- PHP 8.2 or higher
- Pimcore 11.0+ or Pimcore 12.0+
- Symfony 6.4+ or Symfony 7.0+
- MySQL 8.0+ or MariaDB 10.5+
- For Studio UI features: Pimcore Studio UI Bundle

## 🔧 Installation

### Step 1: Install via Composer

```bash
composer require chauhan-mukesh/studio-todo-bundle
```

### Step 2: Enable the Bundle

The bundle should be auto-discovered. If not, add it manually to `config/bundles.php`:

```php
return [
    // ... other bundles
    ChauhanMukesh\StudioTodoBundle\StudioTodoBundle::class => ['all' => true],
];
```

### Step 3: Register Routes

Create `config/routes/studio_todo.yaml` in your Pimcore application:

```yaml
studio_todo_api:
    resource: '@StudioTodoBundle/Resources/config/routes.yaml'
```

### Step 4: Install Bundle

```bash
bin/console pimcore:bundle:install StudioTodoBundle
```

This command will:
- Create the `todo_items` table with all necessary columns and indexes
- Create the `todo_audit_log` table for change tracking
- Register three permissions: `studio_todo_view`, `studio_todo_manage`, `studio_todo_admin`

### Step 5: Clear Cache

```bash
bin/console cache:clear
```

### Step 6: (Optional) Configure the Bundle

Create `config/packages/studio_todo.yaml`:

```yaml
studio_todo:
  enabled: true

  # Default settings for new todos
  defaults:
    status: open
    priority: medium

  # Async processing
  async:
    enabled: true
    queue_name: studio_todo

  # Audit logging
  audit:
    enabled: true
    retention_days: 365

  # Soft delete settings
  soft_delete:
    enabled: true
```

### Step 7: (Optional) Build Studio UI Frontend

If you want to use the React-based Studio UI interface:

```bash
cd vendor/chauhan-mukesh/studio-todo-bundle/assets/studio
npm install
npm run build
bin/console cache:clear
```

## 📊 Database Schema

### `todo_items` Table

The main table storing all todo items with comprehensive fields:

| Column | Type | Description |
|--------|------|-------------|
| `id` | INTEGER | Primary key, auto-increment |
| `title` | VARCHAR(255) | Todo title (required) |
| `description` | TEXT | Detailed description (optional) |
| `status` | VARCHAR(30) | Current status: open, in_progress, completed, cancelled, on_hold |
| `workflow_state` | VARCHAR(50) | Pimcore workflow state (optional) |
| `priority` | VARCHAR(20) | Priority: low, medium, high, critical |
| `related_element_id` | INTEGER | Linked Pimcore element ID (optional) |
| `related_element_type` | VARCHAR(20) | Element type: object, asset, document (optional) |
| `related_class` | VARCHAR(255) | DataObject class name (optional) |
| `assigned_to_user_id` | INTEGER | Assigned user ID (optional) |
| `created_by_user_id` | INTEGER | Creator user ID (optional) |
| `updated_by_user_id` | INTEGER | Last updater user ID (optional) |
| `due_date` | DATETIME | Deadline (optional) |
| `completed_at` | DATETIME | Completion timestamp (optional) |
| `created_at` | DATETIME | Creation timestamp (required) |
| `updated_at` | DATETIME | Last update timestamp (required) |
| `deleted_at` | DATETIME | Soft delete timestamp (optional) |
| `position` | INTEGER | Sort position (default: 0) |
| `category` | VARCHAR(100) | Category for grouping (optional) |
| `meta` | JSON | Flexible metadata (optional) |

**Indexes** for optimal performance:
- `idx_status` on `status`
- `idx_assigned` on `assigned_to_user_id`
- `idx_relation` on `(related_element_id, related_element_type)`
- `idx_created` on `created_at`
- `idx_due` on `due_date`
- `idx_deleted` on `deleted_at`
- `idx_priority` on `priority`
- `idx_category` on `category`

### `todo_audit_log` Table

Complete audit trail for all changes:

| Column | Type | Description |
|--------|------|-------------|
| `id` | INTEGER | Primary key |
| `todo_id` | INTEGER | Related todo item |
| `action` | VARCHAR(50) | Action type: create, update, delete, restore |
| `field_name` | VARCHAR(100) | Changed field name (optional) |
| `old_value` | TEXT | Previous value (optional) |
| `new_value` | TEXT | New value (optional) |
| `user_id` | INTEGER | User who made the change |
| `created_at` | DATETIME | Timestamp |

## 🎯 Usage

### Basic Usage

#### Create a Todo

```php
use ChauhanMukesh\StudioTodoBundle\Service\TodoManager;
use ChauhanMukesh\StudioTodoBundle\Enum\TodoStatus;
use ChauhanMukesh\StudioTodoBundle\Enum\TodoPriority;

// Inject TodoManager via dependency injection
public function __construct(
    private readonly TodoManager $todoManager
) {}

// Create a new todo
$todoId = $this->todoManager->create([
    'title' => 'Review product images',
    'description' => 'Check all images for Product SKU-12345',
    'status' => TodoStatus::Open->value,
    'priority' => TodoPriority::High->value,
    'assigned_to_user_id' => 5,
    'due_date' => new \DateTimeImmutable('+7 days'),
    'category' => 'Content Review',
]);
```

#### Link Todo to a Pimcore Element

```php
use Pimcore\Model\DataObject\Product;

$product = Product::getById(12345);

$todoId = $this->todoManager->create([
    'title' => 'Update product data',
    'related_element_id' => $product->getId(),
    'related_element_type' => 'object',
    'related_class' => 'Product',
    // ... other fields
]);
```

#### Update a Todo

```php
$this->todoManager->update($todoId, [
    'status' => TodoStatus::InProgress->value,
    'assigned_to_user_id' => 7,
]);
```

#### Complete a Todo

```php
$this->todoManager->complete($todoId);
// Sets status to 'completed' and sets completed_at timestamp
```

#### Soft Delete a Todo

```php
$this->todoManager->softDelete($todoId);
// Sets deleted_at timestamp, todo is hidden but recoverable
```

### REST API Endpoints

The bundle exposes a comprehensive REST API at `/pimcore-studio/api/studio-todo/*`:

#### Todo Operations

- `GET /pimcore-studio/api/studio-todo/todos` - List all todos (with filters)
- `GET /pimcore-studio/api/studio-todo/todos/{id}` - Get single todo
- `POST /pimcore-studio/api/studio-todo/todos` - Create new todo
- `PUT /pimcore-studio/api/studio-todo/todos/{id}` - Update todo
- `DELETE /pimcore-studio/api/studio-todo/todos/{id}` - Soft delete todo
- `POST /pimcore-studio/api/studio-todo/todos/{id}/complete` - Mark as completed
- `POST /pimcore-studio/api/studio-todo/todos/{id}/restore` - Restore deleted todo
- `DELETE /pimcore-studio/api/studio-todo/todos/{id}/hard-delete` - Permanently delete (Admin only)

#### Filtering & Search

```bash
# Get open todos assigned to user 5
GET /pimcore-studio/api/studio-todo/todos?status=open&assigned_to_user_id=5

# Get high priority todos due this week
GET /pimcore-studio/api/studio-todo/todos?priority=high&due_before=2026-04-09

# Get todos for a specific element
GET /pimcore-studio/api/studio-todo/todos?related_element_id=123&related_element_type=object

# Search by title
GET /pimcore-studio/api/studio-todo/todos?search=product+review

# Pagination
GET /pimcore-studio/api/studio-todo/todos?page=2&limit=20
```

#### Audit & Statistics

- `GET /pimcore-studio/api/studio-todo/audit/{todoId}` - Get change history
- `GET /pimcore-studio/api/studio-todo/stats` - Get statistics dashboard
- `GET /pimcore-studio/api/studio-todo/stats/by-user` - Stats grouped by user
- `GET /pimcore-studio/api/studio-todo/stats/by-status` - Stats grouped by status

### CLI Commands

#### List Todos

```bash
# List all active todos
bin/console studio-todo:list

# Filter by status
bin/console studio-todo:list --status=open

# Filter by assigned user
bin/console studio-todo:list --assigned-to=5

# Show overdue todos
bin/console studio-todo:list --overdue
```

#### Create Todo via CLI

```bash
bin/console studio-todo:create \
  --title="Review assets" \
  --priority=high \
  --assigned-to=5 \
  --due-date="+3 days"
```

#### Cleanup Old Completed Todos

```bash
# Delete completed todos older than 90 days
bin/console studio-todo:cleanup --days=90 --dry-run

# Actually delete (without --dry-run)
bin/console studio-todo:cleanup --days=90
```

#### Statistics

```bash
bin/console studio-todo:stats
```

## 🔐 Security & Permissions

The bundle implements a three-tier permission system:

### Permission Levels

1. **`studio_todo_view`** - Read-only access
   - View todos
   - View audit logs
   - View statistics

2. **`studio_todo_manage`** - Full todo management
   - All View permissions
   - Create todos
   - Update todos
   - Delete todos
   - Assign todos

3. **`studio_todo_admin`** - Administrative access
   - All Manage permissions
   - Bundle configuration
   - Bulk operations
   - Audit log management

### Assigning Permissions

Permissions can be assigned through the Pimcore admin interface:

1. Go to **Settings → Users & Roles**
2. Select a user or role
3. Find **Studio Todo** in the permissions list
4. Check the appropriate permission levels

### Code-Level Permission Checks

All API endpoints and services automatically check permissions. In custom code:

```php
use Pimcore\Bundle\StudioBackendBundle\Security\Service\SecurityServiceInterface;
use ChauhanMukesh\StudioTodoBundle\Enum\TodoPermission;

public function __construct(
    private readonly SecurityServiceInterface $security
) {}

public function someAction(): void
{
    $user = $this->security->getCurrentUser();

    if (!$user->isAllowed(TodoPermission::Manage->value)) {
        throw new AccessDeniedException('Insufficient permissions');
    }

    // ... proceed with operation
}
```

## 🎨 Studio UI Integration

### Features

The React-based Studio UI provides:

- 📋 **Dashboard Tab**: Overview with statistics, recent todos, and quick actions
- ✅ **Todo List**: Full CRUD interface with inline editing
- 📊 **Statistics View**: Charts and metrics for productivity tracking
- 🔍 **Advanced Filters**: Multi-field filtering and search
- 🎯 **Drag & Drop**: Reorder todos by priority
- 🌐 **Real-time Updates**: Mercure integration for live sync
- 🌍 **Multi-language**: English and German translations included

### Accessing the UI

After installation and building the frontend:

1. Log into Pimcore Admin
2. Navigate to **Tools → Studio Todo** in the main menu
3. The todo management interface will open in a new tab

### Customizing the UI

The React components are fully customizable. Source files are located in:

```
vendor/chauhan-mukesh/studio-todo-bundle/assets/studio/js/src/modules/todo-bundle/
```

To modify:

1. Edit the TypeScript/React files
2. Rebuild: `cd assets/studio && npm run build`
3. Clear Pimcore cache: `bin/console cache:clear`

## 🔄 Real-time Updates (Mercure SSE)

Live synchronization uses [Mercure](https://mercure.rocks/) Server-Sent Events.

### Requirements

```bash
composer require symfony/mercure-bundle
```

### Configuration

```yaml
# config/packages/mercure.yaml
mercure:
  hubs:
    default:
      url: https://your-mercure-hub/.well-known/mercure
      jwt:
        secret: '%env(MERCURE_JWT_SECRET)%'
```

### How It Works

- `MercurePublisher` service publishes updates to topics `studio-todo/todos` and `studio-todo/todo/{id}`
- The frontend `useMercureSSE` hook subscribes and automatically refreshes the todo list
- The service is optional: if `mercure.hub.default` is not registered, it silently no-ops

### Frontend SSE URL

The hub URL can be configured by setting `window.MERCURE_HUB_URL` before the bundle JS loads:

```html
<script>window.MERCURE_HUB_URL = '/.well-known/mercure';</script>
```

## 🧪 Testing

### Running PHP Tests

```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test suite
./vendor/bin/phpunit tests/Unit/Service/TodoManagerTest.php

# With coverage
./vendor/bin/phpunit --coverage-html coverage/
```

### Test Structure

```
tests/
├── Unit/
│   ├── Service/
│   │   ├── TodoManagerTest.php
│   │   └── AuditLoggerTest.php
│   ├── Model/
│   │   └── TodoItemTest.php
│   └── Repository/
│       └── TodoRepositoryTest.php
└── Integration/
    └── Api/
        └── TodoControllerTest.php
```

### Running Frontend Tests

```bash
cd assets/studio

# Install dependencies
npm install

# Run tests with Vitest
npm test

# Watch mode
npm run test:watch

# With coverage
npm run test:coverage
```

## 🔧 Configuration Reference

### Complete Configuration Example

```yaml
# config/packages/studio_todo.yaml
studio_todo:
  # Global enable/disable
  enabled: true

  # Default values for new todos
  defaults:
    status: open
    priority: medium
    category: null

  # Async processing via Symfony Messenger
  async:
    enabled: true
    queue_name: studio_todo
    batch_size: 50

  # Audit logging configuration
  audit:
    enabled: true
    retention_days: 365  # Keep logs for 1 year
    log_read_operations: false  # Don't log view operations

  # Soft delete settings
  soft_delete:
    enabled: true
    auto_cleanup_days: 90  # Permanently delete after 90 days

  # Notification settings (if notification bundle is installed)
  notifications:
    enabled: true
    channels: [email, internal]
    notify_on_assignment: true
    notify_on_due_date: true
    reminder_before_days: 3

  # Workflow integration
  workflow:
    enabled: true
    default_workflow: todo_workflow

  # UI settings
  ui:
    items_per_page: 20
    enable_realtime: true  # Requires Mercure
    default_sort: due_date
    default_order: asc
```

### Messenger Configuration

To enable async processing:

```yaml
# config/packages/messenger.yaml
framework:
  messenger:
    transports:
      studio_todo:
        dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
        options:
          queue_name: studio_todo
        retry_strategy:
          max_retries: 3
          delay: 1000
          multiplier: 2

    routing:
      'ChauhanMukesh\StudioTodoBundle\Message\*': studio_todo
```

## 📦 Architecture Overview

### Directory Structure

```
src/
├── Command/                  # CLI commands
│   ├── ListCommand.php
│   ├── CreateCommand.php
│   ├── CleanupCommand.php
│   └── StatsCommand.php
├── Controller/Api/           # REST API controllers
│   ├── TodoController.php
│   ├── AuditController.php
│   └── StatsController.php
├── DependencyInjection/      # Symfony DI configuration
│   ├── Configuration.php
│   └── StudioTodoExtension.php
├── Enum/                     # Value objects (backed enums)
│   ├── TodoStatus.php
│   ├── TodoPriority.php
│   ├── TodoPermission.php
│   └── TriggerType.php
├── Event/                    # Custom events
│   ├── TodoEvents.php
│   └── TodoEvent.php
├── EventListener/            # Event subscribers
│   └── TodoEventListener.php
├── Installer/                # Bundle installer
│   └── Installer.php
├── Message/                  # Async messages
│   └── TodoOperationMessage.php
├── MessageHandler/           # Message handlers
│   └── TodoOperationHandler.php
├── Model/                    # Domain models
│   └── TodoItem.php
├── Repository/               # Data access layer
│   ├── TodoRepository.php
│   └── AuditRepository.php
├── Resources/                # Configuration and translations
│   ├── config/
│   │   ├── routes.yaml
│   │   └── services.yaml
│   └── translations/
│       ├── StudioTodoBundle.en.yaml
│       ├── StudioTodoBundle.de.yaml
│       └── StudioTodoBundle.fr.yaml
├── Service/                  # Business logic
│   ├── TodoManager.php
│   ├── AuditLogger.php
│   ├── StatisticsService.php
│   └── MercurePublisher.php
└── StudioTodoBundle.php      # Main bundle class

assets/studio/                # React/TypeScript frontend
├── js/src/
│   └── modules/todo-bundle/
│       ├── components/       # React UI components
│       │   ├── Dashboard.tsx
│       │   └── TodoList.tsx
│       ├── hooks/            # Custom React hooks
│       │   └── useMercureSSE.ts
│       ├── services/         # API client
│       │   └── todoApi.ts
│       └── types/            # Shared TypeScript types
│           └── index.ts
├── package.json
├── tsconfig.json
└── rsbuild.config.ts
```

### Key Design Patterns

1. **Repository Pattern**: Data access abstraction
2. **Service Layer**: Business logic encapsulation
3. **Event-Driven Architecture**: Extensibility via events
4. **Enum Value Objects**: Type-safe constants
5. **Immutable Models**: Readonly properties for data integrity
6. **Dependency Injection**: All services autowired
7. **Async Processing**: Message queue for scalability

## 🚀 Performance Optimization

### Database Indexes

All critical queries are optimized with indexes:
- Lookups by status, priority, user, element relation
- Date-based queries (created, updated, due dates)
- Soft delete filtering

### Async Processing

Heavy operations can be offloaded to message queue:
- Bulk operations
- Notification sending
- Report generation
- Cleanup tasks

### Caching Strategy

```php
# Enable caching for statistics
studio_todo:
  cache:
    enabled: true
    ttl: 300  # 5 minutes
    adapter: cache.app  # Use Symfony cache pool
```

## 🌍 Internationalization

### Supported Languages

- English (`en`)
- German (`de`)
- French (`fr`)

### PHP Backend Translations

Translation files are located in `src/Resources/translations/`:

```
src/Resources/translations/
├── StudioTodoBundle.en.yaml  ← English (default)
├── StudioTodoBundle.de.yaml  ← German
└── StudioTodoBundle.fr.yaml  ← French
```

### Adding New Translations

1. Copy an existing file: `cp src/Resources/translations/StudioTodoBundle.en.yaml src/Resources/translations/StudioTodoBundle.es.yaml`
2. Translate all values
3. Clear Pimcore cache: `bin/console cache:clear`

### Frontend i18n

For frontend localization, Ant Design's `ConfigProvider` locale prop can be set:

```tsx
import esES from 'antd/locale/es_ES';

<ConfigProvider locale={esES}>
  <App>...</App>
</ConfigProvider>
```

## 🤝 Contributing

Contributions are welcome! Please follow these guidelines:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Development Setup

```bash
# Clone the repository
git clone https://github.com/Chauhan-Mukesh/studio-todo-bundle.git
cd studio-todo-bundle

# Install dependencies
composer install
cd assets/studio && npm install

# Run tests
composer test

# Build frontend
npm run build
```

## 📝 License

This bundle is released under the MIT License. See the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Built following the [Pimcore Bundle Developer Guide](https://pimcore.com/docs/platform/Pimcore/Development_Tools_and_Details/Bundles/Pimcore_Bundles/)
- Inspired by the excellent [Pimcore Asset Pilot Bundle](https://github.com/oronts/pimcore-asset-pilot-bundle) architecture
- React/TypeScript frontend based on [Pimcore Studio UI patterns](https://pimcore.com/docs/platform/Studio_UI/)

## 📞 Support

- **Documentation**: [Full documentation](https://github.com/Chauhan-Mukesh/studio-todo-bundle/wiki)
- **Issues**: [GitHub Issues](https://github.com/Chauhan-Mukesh/studio-todo-bundle/issues)
- **Discussions**: [GitHub Discussions](https://github.com/Chauhan-Mukesh/studio-todo-bundle/discussions)

---

**Built with ❤️ for the Pimcore community**
