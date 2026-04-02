<?php

/**
 * Studio Todo Bundle for Pimcore 12+
 *
 * @license MIT
 * @author Mukesh Chauhan
 */

declare(strict_types=1);

namespace ChauhanMukesh\StudioTodoBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Dependency Injection Extension
 *
 * Loads service configuration and processes bundle configuration
 */
class StudioTodoExtension extends Extension
{
    /**
     * Load service definitions and process configuration
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Store configuration as container parameters for easy access
        $container->setParameter('studio_todo.config', $config);
        $container->setParameter('studio_todo.enabled', $config['enabled']);
        $container->setParameter('studio_todo.defaults', $config['defaults']);
        $container->setParameter('studio_todo.async', $config['async']);
        $container->setParameter('studio_todo.audit', $config['audit']);
        $container->setParameter('studio_todo.soft_delete', $config['soft_delete']);
        $container->setParameter('studio_todo.notifications', $config['notifications']);
        $container->setParameter('studio_todo.workflow', $config['workflow']);
        $container->setParameter('studio_todo.ui', $config['ui']);
        $container->setParameter('studio_todo.cache', $config['cache']);

        // Load service definitions from YAML
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );
        $loader->load('services.yaml');
    }

    /**
     * Return the configuration alias (studio_todo)
     */
    public function getAlias(): string
    {
        return 'studio_todo';
    }
}
