<?php

/**
 * Studio Todo Bundle for Pimcore 12+
 *
 * @license MIT
 * @author Mukesh Chauhan
 */

declare(strict_types=1);

namespace ChauhanMukesh\StudioTodoBundle\Controller\Api;

use ChauhanMukesh\StudioTodoBundle\Enum\TodoPermission;
use ChauhanMukesh\StudioTodoBundle\Service\StatisticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Stats Controller - REST API for statistics
 *
 * Provides endpoints for viewing statistics and analytics
 */
#[Route('/pimcore-studio/api/studio-todo/stats', name: 'studio_todo_api_stats_')]
class StatsController extends AbstractController
{
    public function __construct(
        private readonly StatisticsService $statsService
    ) {
    }

    /**
     * Get overall statistics
     */
    #[Route('', name: 'overall', methods: ['GET'])]
    public function overall(): JsonResponse
    {
        $this->denyAccessUnlessGranted(TodoPermission::View->value);
        $stats = $this->statsService->getOverallStatistics();

        return new JsonResponse([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get statistics grouped by user
     */
    #[Route('/by-user', name: 'by_user', methods: ['GET'])]
    public function byUser(): JsonResponse
    {
        $this->denyAccessUnlessGranted(TodoPermission::View->value);
        $stats = $this->statsService->getStatisticsByUser();

        return new JsonResponse([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get statistics grouped by status
     */
    #[Route('/by-status', name: 'by_status', methods: ['GET'])]
    public function byStatus(): JsonResponse
    {
        $this->denyAccessUnlessGranted(TodoPermission::View->value);
        $stats = $this->statsService->getStatisticsByStatus();

        return new JsonResponse([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get statistics grouped by priority
     */
    #[Route('/by-priority', name: 'by_priority', methods: ['GET'])]
    public function byPriority(): JsonResponse
    {
        $this->denyAccessUnlessGranted(TodoPermission::View->value);
        $stats = $this->statsService->getStatisticsByPriority();

        return new JsonResponse([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get statistics grouped by category
     */
    #[Route('/by-category', name: 'by_category', methods: ['GET'])]
    public function byCategory(): JsonResponse
    {
        $this->denyAccessUnlessGranted(TodoPermission::View->value);
        $stats = $this->statsService->getStatisticsByCategory();

        return new JsonResponse([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
