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
use ChauhanMukesh\StudioTodoBundle\Repository\AuditRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Audit Controller - REST API for audit logs
 *
 * Provides endpoints for viewing audit history
 */
#[Route('/pimcore-studio/api/studio-todo/audit', name: 'studio_todo_api_audit_')]
class AuditController extends AbstractController
{
    public function __construct(
        private readonly AuditRepository $auditRepository
    ) {
    }

    /**
     * Get audit log for a specific todo
     */
    #[Route('/{todoId}', name: 'get_by_todo', methods: ['GET'], requirements: ['todoId' => '\d+'])]
    public function getByTodo(int $todoId, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(TodoPermission::View->value);
        $limit = min((int) $request->query->get('limit', 100), 500);
        $page = max((int) $request->query->get('page', 1), 1);
        $offset = ($page - 1) * $limit;

        $entries = $this->auditRepository->getByTodoId($todoId, $limit, $offset);
        $total = $this->auditRepository->count(['todo_id' => $todoId]);

        return new JsonResponse([
            'success' => true,
            'data' => $entries,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit),
            ],
        ]);
    }

    /**
     * Get all audit entries with filtering
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(TodoPermission::View->value);
        $filters = [];

        if ($request->query->has('todo_id')) {
            $filters['todo_id'] = (int) $request->query->get('todo_id');
        }

        if ($request->query->has('action')) {
            $filters['action'] = $request->query->get('action');
        }

        if ($request->query->has('user_id')) {
            $filters['user_id'] = (int) $request->query->get('user_id');
        }

        if ($request->query->has('from_date')) {
            $filters['from_date'] = $request->query->get('from_date');
        }

        if ($request->query->has('to_date')) {
            $filters['to_date'] = $request->query->get('to_date');
        }

        $limit = min((int) $request->query->get('limit', 100), 500);
        $page = max((int) $request->query->get('page', 1), 1);
        $offset = ($page - 1) * $limit;

        $entries = $this->auditRepository->findAll($filters, $limit, $offset);
        $total = $this->auditRepository->count($filters);

        return new JsonResponse([
            'success' => true,
            'data' => $entries,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit),
            ],
        ]);
    }
}
