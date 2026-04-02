<?php

/**
 * Studio Todo Bundle for Pimcore 12+
 *
 * @license MIT
 * @author Mukesh Chauhan
 */

declare(strict_types=1);

namespace ChauhanMukesh\StudioTodoBundle\Installer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use ChauhanMukesh\StudioTodoBundle\Enum\TodoPermission;
use Pimcore\Extension\Bundle\Installer\SettingsStoreAwareInstaller;
use Pimcore\Model\User\Permission\Definition;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * Bundle installer - handles database schema and permissions
 *
 * Creates the todo_items table with full Pimcore integration support:
 * - Workflow states
 * - User assignments
 * - Element relations
 * - Soft deletes
 * - Full audit trail
 */
class Installer extends SettingsStoreAwareInstaller
{
    public const string TABLE_TODO_ITEMS = 'todo_items';
    public const string TABLE_TODO_AUDIT_LOG = 'todo_audit_log';

    public function __construct(
        BundleInterface $bundle,
        protected readonly Connection $db,
    ) {
        parent::__construct($bundle);
    }

    /**
     * Install bundle - create database tables and register permissions
     */
    public function install(): void
    {
        $schemaManager = $this->db->createSchemaManager();
        $currentSchema = $schemaManager->introspectSchema();
        $schema = clone $currentSchema;

        // Create todo_items table
        if (!$schema->hasTable(self::TABLE_TODO_ITEMS)) {
            $this->createTodoItemsTable($schema);
        }

        // Create audit log table
        if (!$schema->hasTable(self::TABLE_TODO_AUDIT_LOG)) {
            $this->createAuditLogTable($schema);
        }

        // Apply schema changes
        $comparator = $schemaManager->createComparator();
        $schemaDiff = $comparator->compareSchemas($currentSchema, $schema);

        foreach ($this->db->getDatabasePlatform()->getAlterSchemaSQL($schemaDiff) as $sql) {
            $this->db->executeStatement($sql);
        }

        // Register permissions
        foreach (TodoPermission::cases() as $permission) {
            $def = Definition::getByKey($permission->value);
            if ($def === null) {
                Definition::create($permission->value)
                    ->setCategory(TodoPermission::CATEGORY)
                    ->save();
            }
        }

        parent::install();
    }

    /**
     * Create the main todo_items table
     */
    protected function createTodoItemsTable(Schema $schema): void
    {
        $table = $schema->createTable(self::TABLE_TODO_ITEMS);

        // Primary key
        $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);

        // Core fields
        $table->addColumn('title', 'string', ['length' => 255, 'notnull' => true]);
        $table->addColumn('description', 'text', ['notnull' => false]);

        // Status and workflow
        $table->addColumn('status', 'string', ['length' => 30, 'notnull' => true]);
        $table->addColumn('workflow_state', 'string', ['length' => 50, 'notnull' => false]);

        // Priority
        $table->addColumn('priority', 'string', ['length' => 20, 'notnull' => true]);

        // Pimcore element relations
        $table->addColumn('related_element_id', 'integer', ['notnull' => false]);
        $table->addColumn('related_element_type', 'string', ['length' => 20, 'notnull' => false]);
        $table->addColumn('related_class', 'string', ['length' => 255, 'notnull' => false]);

        // User assignments
        $table->addColumn('assigned_to_user_id', 'integer', ['notnull' => false]);
        $table->addColumn('created_by_user_id', 'integer', ['notnull' => false]);
        $table->addColumn('updated_by_user_id', 'integer', ['notnull' => false]);

        // Dates
        $table->addColumn('due_date', 'datetime_immutable', ['notnull' => false]);
        $table->addColumn('completed_at', 'datetime_immutable', ['notnull' => false]);
        $table->addColumn('created_at', 'datetime_immutable', ['notnull' => true]);
        $table->addColumn('updated_at', 'datetime_immutable', ['notnull' => true]);
        $table->addColumn('deleted_at', 'datetime_immutable', ['notnull' => false]);

        // UI and grouping
        $table->addColumn('position', 'integer', ['default' => 0, 'notnull' => true]);
        $table->addColumn('category', 'string', ['length' => 100, 'notnull' => false]);

        // Flexible metadata
        $table->addColumn('meta', 'json', ['notnull' => false]);

        // Primary key
        $table->setPrimaryKey(['id']);

        // Indexes for performance
        $table->addIndex(['status'], 'idx_status');
        $table->addIndex(['assigned_to_user_id'], 'idx_assigned');
        $table->addIndex(['related_element_id', 'related_element_type'], 'idx_relation');
        $table->addIndex(['created_at'], 'idx_created');
        $table->addIndex(['due_date'], 'idx_due');
        $table->addIndex(['deleted_at'], 'idx_deleted');
        $table->addIndex(['priority'], 'idx_priority');
        $table->addIndex(['category'], 'idx_category');
    }

    /**
     * Create the audit log table for change tracking
     */
    protected function createAuditLogTable(Schema $schema): void
    {
        $table = $schema->createTable(self::TABLE_TODO_AUDIT_LOG);

        $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $table->addColumn('todo_id', 'integer', ['notnull' => true]);
        $table->addColumn('action', 'string', ['length' => 50, 'notnull' => true]);
        $table->addColumn('field_name', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('old_value', 'text', ['notnull' => false]);
        $table->addColumn('new_value', 'text', ['notnull' => false]);
        $table->addColumn('user_id', 'integer', ['notnull' => false]);
        $table->addColumn('created_at', 'datetime_immutable', ['notnull' => true]);

        $table->setPrimaryKey(['id']);
        $table->addIndex(['todo_id'], 'idx_audit_todo');
        $table->addIndex(['action'], 'idx_audit_action');
        $table->addIndex(['created_at'], 'idx_audit_created');
    }

    /**
     * Uninstall bundle - remove tables and permissions
     */
    public function uninstall(): void
    {
        $schemaManager = $this->db->createSchemaManager();
        $currentSchema = $schemaManager->introspectSchema();
        $schema = clone $currentSchema;

        if ($schema->hasTable(self::TABLE_TODO_AUDIT_LOG)) {
            $schema->dropTable(self::TABLE_TODO_AUDIT_LOG);
        }

        if ($schema->hasTable(self::TABLE_TODO_ITEMS)) {
            $schema->dropTable(self::TABLE_TODO_ITEMS);
        }

        $comparator = $schemaManager->createComparator();
        $schemaDiff = $comparator->compareSchemas($currentSchema, $schema);
        foreach ($this->db->getDatabasePlatform()->getAlterSchemaSQL($schemaDiff) as $sql) {
            $this->db->executeStatement($sql);
        }

        // Remove permissions
        foreach (TodoPermission::cases() as $permission) {
            $def = Definition::getByKey($permission->value);
            if ($def !== null) {
                $def->delete();
            }
        }

        parent::uninstall();
    }
}
