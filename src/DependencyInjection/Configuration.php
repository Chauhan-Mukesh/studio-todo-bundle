<?php

/**
 * Studio Todo Bundle for Pimcore 12+
 *
 * @license MIT
 * @author Mukesh Chauhan
 */

declare(strict_types=1);

namespace ChauhanMukesh\StudioTodoBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Bundle configuration definition using TreeBuilder
 *
 * Defines the configuration structure for studio_todo.yaml
 */
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('studio_todo');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                // Global enable/disable
                ->booleanNode('enabled')
                    ->defaultTrue()
                    ->info('Enable or disable the todo bundle globally')
                ->end()

                // Default values for new todos
                ->arrayNode('defaults')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('status')
                            ->values(['open', 'in_progress', 'completed', 'cancelled', 'on_hold'])
                            ->defaultValue('open')
                            ->info('Default status for new todos')
                        ->end()
                        ->enumNode('priority')
                            ->values(['low', 'medium', 'high', 'critical'])
                            ->defaultValue('medium')
                            ->info('Default priority for new todos')
                        ->end()
                        ->scalarNode('category')
                            ->defaultNull()
                            ->info('Default category for new todos')
                        ->end()
                    ->end()
                ->end()

                // Async processing configuration
                ->arrayNode('async')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                            ->info('Enable async processing via Symfony Messenger')
                        ->end()
                        ->scalarNode('queue_name')
                            ->defaultValue('studio_todo')
                            ->info('Message queue name')
                        ->end()
                        ->integerNode('batch_size')
                            ->defaultValue(50)
                            ->min(1)
                            ->info('Batch size for bulk operations')
                        ->end()
                    ->end()
                ->end()

                // Audit logging configuration
                ->arrayNode('audit')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                            ->info('Enable audit logging')
                        ->end()
                        ->integerNode('retention_days')
                            ->defaultValue(365)
                            ->min(1)
                            ->info('Number of days to keep audit logs')
                        ->end()
                        ->booleanNode('log_read_operations')
                            ->defaultFalse()
                            ->info('Log read operations (can be verbose)')
                        ->end()
                    ->end()
                ->end()

                // Soft delete configuration
                ->arrayNode('soft_delete')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                            ->info('Enable soft delete functionality')
                        ->end()
                        ->integerNode('auto_cleanup_days')
                            ->defaultValue(90)
                            ->min(0)
                            ->info('Days before permanently deleting soft-deleted items (0 = never)')
                        ->end()
                    ->end()
                ->end()

                // Notification settings
                ->arrayNode('notifications')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultFalse()
                            ->info('Enable notifications')
                        ->end()
                        ->arrayNode('channels')
                            ->scalarPrototype()->end()
                            ->defaultValue(['email', 'internal'])
                            ->info('Notification channels')
                        ->end()
                        ->booleanNode('notify_on_assignment')
                            ->defaultTrue()
                            ->info('Send notification when todo is assigned')
                        ->end()
                        ->booleanNode('notify_on_due_date')
                            ->defaultTrue()
                            ->info('Send notification when due date approaches')
                        ->end()
                        ->integerNode('reminder_before_days')
                            ->defaultValue(3)
                            ->min(0)
                            ->info('Days before due date to send reminder')
                        ->end()
                    ->end()
                ->end()

                // Workflow integration
                ->arrayNode('workflow')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultFalse()
                            ->info('Enable Pimcore workflow integration')
                        ->end()
                        ->scalarNode('default_workflow')
                            ->defaultValue('todo_workflow')
                            ->info('Default workflow name')
                        ->end()
                    ->end()
                ->end()

                // UI settings
                ->arrayNode('ui')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('items_per_page')
                            ->defaultValue(20)
                            ->min(1)
                            ->max(100)
                            ->info('Number of items per page in UI')
                        ->end()
                        ->booleanNode('enable_realtime')
                            ->defaultFalse()
                            ->info('Enable real-time updates (requires Mercure)')
                        ->end()
                        ->scalarNode('default_sort')
                            ->defaultValue('due_date')
                            ->info('Default sort field')
                        ->end()
                        ->enumNode('default_order')
                            ->values(['asc', 'desc'])
                            ->defaultValue('asc')
                            ->info('Default sort order')
                        ->end()
                    ->end()
                ->end()

                // Cache configuration
                ->arrayNode('cache')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                            ->info('Enable caching for statistics')
                        ->end()
                        ->integerNode('ttl')
                            ->defaultValue(300)
                            ->min(0)
                            ->info('Cache TTL in seconds')
                        ->end()
                        ->scalarNode('adapter')
                            ->defaultValue('cache.app')
                            ->info('Symfony cache adapter service name')
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
