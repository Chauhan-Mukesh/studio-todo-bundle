<?php

/**
 * Studio Todo Bundle for Pimcore 12+
 *
 * @license MIT
 * @author Mukesh Chauhan
 */

declare(strict_types=1);

namespace ChauhanMukesh\StudioTodoBundle;

use Doctrine\DBAL\Connection;
use Pimcore\Extension\Bundle\AbstractPimcoreBundle;
use Pimcore\Extension\Bundle\Traits\PackageVersionTrait;
use ChauhanMukesh\StudioTodoBundle\Installer\Installer;

/**
 * Main bundle class for Studio Todo Bundle
 *
 * This bundle provides comprehensive todo/task management functionality for Pimcore 12+
 * with full Studio UI integration, real-time updates, and workflow support.
 */
class StudioTodoBundle extends AbstractPimcoreBundle
{
    use PackageVersionTrait;

    /**
     * Returns the bundle's root path
     *
     * @return string
     */
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    /**
     * Returns the composer package name
     *
     * @return string
     */
    protected function getComposerPackageName(): string
    {
        return 'chauhan-mukesh/studio-todo-bundle';
    }

    /**
     * Returns the bundle installer
     *
     * @return Installer
     */
    public function getInstaller(): Installer
    {
        /** @var Connection $connection */
        $connection = $this->container->get(Connection::class);

        return new Installer($this, $connection);
    }
}
