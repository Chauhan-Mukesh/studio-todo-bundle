<?php

/**
 * Studio Todo Bundle for Pimcore 12+
 *
 * @license MIT
 * @author Mukesh Chauhan
 */

declare(strict_types=1);

namespace ChauhanMukesh\StudioTodoBundle\Service;

use ChauhanMukesh\StudioTodoBundle\Repository\TodoRepository;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Statistics Service
 *
 * Provides statistics and analytics for todos
 */
class StatisticsService
{
    private const CACHE_KEY_PREFIX = 'studio_todo_stats_';

    public function __construct(
        private readonly TodoRepository $repository,
        private readonly CacheItemPoolInterface $cache,
        private readonly array $config
    ) {
    }

    /**
     * Get overall statistics
     */
    public function getOverallStatistics(): array
    {
        $cacheKey = self::CACHE_KEY_PREFIX . 'overall';
        $item = null;

        if ($this->isCacheEnabled()) {
            $item = $this->cache->getItem($cacheKey);
            if ($item->isHit()) {
                return $item->get();
            }
        }

        $stats = $this->repository->getStatistics();

        // Calculate percentages
        $total = (int) $stats['total'];
        if ($total > 0) {
            $stats['open_percentage'] = round(($stats['open'] / $total) * 100, 2);
            $stats['in_progress_percentage'] = round(($stats['in_progress'] / $total) * 100, 2);
            $stats['completed_percentage'] = round(($stats['completed'] / $total) * 100, 2);
            $stats['overdue_percentage'] = round(($stats['overdue'] / $total) * 100, 2);
        }

        // Cache the result
        if ($this->isCacheEnabled() && $item !== null) {
            $item->set($stats);
            $item->expiresAfter($this->getCacheTtl());
            $this->cache->save($item);
        }

        return $stats;
    }

    /**
     * Get statistics grouped by user
     */
    public function getStatisticsByUser(): array
    {
        $cacheKey = self::CACHE_KEY_PREFIX . 'by_user';
        $item = null;

        if ($this->isCacheEnabled()) {
            $item = $this->cache->getItem($cacheKey);
            if ($item->isHit()) {
                return $item->get();
            }
        }

        $stats = $this->repository->getStatisticsByUser();

        // Cache the result
        if ($this->isCacheEnabled() && $item !== null) {
            $item->set($stats);
            $item->expiresAfter($this->getCacheTtl());
            $this->cache->save($item);
        }

        return $stats;
    }

    /**
     * Get statistics grouped by status
     */
    public function getStatisticsByStatus(): array
    {
        $overall = $this->getOverallStatistics();

        return [
            ['status' => 'open', 'count' => $overall['open']],
            ['status' => 'in_progress', 'count' => $overall['in_progress']],
            ['status' => 'completed', 'count' => $overall['completed']],
            ['status' => 'cancelled', 'count' => $overall['cancelled']],
            ['status' => 'on_hold', 'count' => $overall['on_hold']],
        ];
    }

    /**
     * Get statistics grouped by priority
     */
    public function getStatisticsByPriority(): array
    {
        $cacheKey = self::CACHE_KEY_PREFIX . 'by_priority';
        $item = null;

        if ($this->isCacheEnabled()) {
            $item = $this->cache->getItem($cacheKey);
            if ($item->isHit()) {
                return $item->get();
            }
        }

        $stats = [];
        foreach (['low', 'medium', 'high', 'critical'] as $priority) {
            $count = $this->repository->count(['priority' => $priority]);
            $stats[] = ['priority' => $priority, 'count' => $count];
        }

        // Cache the result
        if ($this->isCacheEnabled() && $item !== null) {
            $item->set($stats);
            $item->expiresAfter($this->getCacheTtl());
            $this->cache->save($item);
        }

        return $stats;
    }

    /**
     * Get statistics grouped by category
     */
    public function getStatisticsByCategory(): array
    {
        $cacheKey = self::CACHE_KEY_PREFIX . 'by_category';
        $item = null;

        if ($this->isCacheEnabled()) {
            $item = $this->cache->getItem($cacheKey);
            if ($item->isHit()) {
                return $item->get();
            }
        }

        $stats = $this->repository->getStatisticsByCategory();

        // Cache the result
        if ($this->isCacheEnabled() && $item !== null) {
            $item->set($stats);
            $item->expiresAfter($this->getCacheTtl());
            $this->cache->save($item);
        }

        return $stats;
    }

    /**
     * Clear statistics cache
     */
    public function clearCache(): void
    {
        $keys = [
            self::CACHE_KEY_PREFIX . 'overall',
            self::CACHE_KEY_PREFIX . 'by_user',
            self::CACHE_KEY_PREFIX . 'by_priority',
            self::CACHE_KEY_PREFIX . 'by_category',
        ];

        foreach ($keys as $key) {
            $this->cache->deleteItem($key);
        }
    }

    /**
     * Check if caching is enabled
     */
    private function isCacheEnabled(): bool
    {
        return $this->config['enabled'] ?? true;
    }

    /**
     * Get cache TTL
     */
    private function getCacheTtl(): int
    {
        return $this->config['ttl'] ?? 300;
    }
}
