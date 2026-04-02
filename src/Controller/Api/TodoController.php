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
use ChauhanMukesh\StudioTodoBundle\Service\TodoManager;
use ChauhanMukesh\StudioTodoBundle\Repository\TodoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Todo Controller - REST API for todo operations
 *
 * Provides full CRUD endpoints for todo management
 */
#[Route('/pimcore-studio/api/studio-todo', name: 'studio_todo_api_')]
class TodoController extends AbstractController
{
    public function __construct(
        private readonly TodoManager $todoManager,
        private readonly TodoRepository $repository
    ) {
    }

    /**
     * List all todos with filtering
     */
    #[Route('/todos', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(TodoPermission::View->value);
        $filters = [];

        // Extract filters from query parameters
        if ($request->query->has('status')) {
            $filters['status'] = $request->query->get('status');
        }

        if ($request->query->has('priority')) {
            $filters['priority'] = $request->query->get('priority');
        }

        if ($request->query->has('assigned_to_user_id')) {
            $filters['assigned_to_user_id'] = (int) $request->query->get('assigned_to_user_id');
        }

        if ($request->query->has('category')) {
            $filters['category'] = $request->query->get('category');
        }

        if ($request->query->has('related_element_id')) {
            $filters['related_element_id'] = (int) $request->query->get('related_element_id');
        }

        if ($request->query->has('related_element_type')) {
            $filters['related_element_type'] = $request->query->get('related_element_type');
        }

        if ($request->query->has('search')) {
            $filters['search'] = $request->query->get('search');
        }

        if ($request->query->has('overdue')) {
            $filters['overdue'] = (bool) $request->query->get('overdue');
        }

        if ($request->query->has('due_before')) {
            $filters['due_before'] = $request->query->get('due_before');
        }

        if ($request->query->has('due_after')) {
            $filters['due_after'] = $request->query->get('due_after');
        }

        // Pagination
        $limit = min((int) $request->query->get('limit', 20), 100);
        $page = max((int) $request->query->get('page', 1), 1);
        $offset = ($page - 1) * $limit;

        // Sorting
        $filters['sort'] = $request->query->get('sort', 'created_at');
        $filters['order'] = strtoupper($request->query->get('order', 'DESC'));

        $todos = $this->todoManager->findAll($filters, $limit, $offset);
        $total = $this->todoManager->count($filters);

        return new JsonResponse([
            'success' => true,
            'data' => array_map(fn($todo) => $todo->toArray(), $todos),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit),
            ],
        ]);
    }

    /**
     * Get a single todo by ID
     */
    #[Route('/todos/{id}', name: 'get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function get(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(TodoPermission::View->value);
        $todo = $this->todoManager->findById($id);

        if (!$todo) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Todo not found',
            ], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'success' => true,
            'data' => $todo->toArray(),
        ]);
    }

    /**
     * Create a new todo
     */
    #[Route('/todos', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(TodoPermission::Manage->value);

        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Invalid JSON body',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!isset($data['title']) || empty($data['title'])) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Title is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $userId = $this->getUserId();
            $todoId = $this->todoManager->create($data, $userId);
            $todo = $this->todoManager->findById($todoId);

            return new JsonResponse([
                'success' => true,
                'data' => $todo->toArray(),
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update a todo
     */
    #[Route('/todos/{id}', name: 'update', methods: ['PUT', 'PATCH'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(TodoPermission::Manage->value);

        $todo = $this->todoManager->findById($id);

        if (!$todo) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Todo not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Invalid JSON body',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $userId = $this->getUserId();
            $success = $this->todoManager->update($id, $data, $userId);

            if (!$success) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Failed to update todo',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $updatedTodo = $this->todoManager->findById($id);

            return new JsonResponse([
                'success' => true,
                'data' => $updatedTodo->toArray(),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a todo (soft delete)
     */
    #[Route('/todos/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(TodoPermission::Manage->value);
        $todo = $this->todoManager->findById($id);

        if (!$todo) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Todo not found',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $userId = $this->getUserId();
            $success = $this->todoManager->softDelete($id, $userId);

            if (!$success) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Failed to delete todo',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return new JsonResponse([
                'success' => true,
                'message' => 'Todo deleted successfully',
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Complete a todo
     */
    #[Route('/todos/{id}/complete', name: 'complete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function complete(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(TodoPermission::Manage->value);
        $todo = $this->todoManager->findById($id);

        if (!$todo) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Todo not found',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $userId = $this->getUserId();
            $success = $this->todoManager->complete($id, $userId);

            if (!$success) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Failed to complete todo',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $completedTodo = $this->todoManager->findById($id);

            return new JsonResponse([
                'success' => true,
                'data' => $completedTodo->toArray(),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Restore a soft-deleted todo
     */
    #[Route('/todos/{id}/restore', name: 'restore', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function restore(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(TodoPermission::Manage->value);
        $todo = $this->todoManager->findById($id, includeDeleted: true);

        if (!$todo) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Todo not found',
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$todo->isDeleted()) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Todo is not deleted',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $userId = $this->getUserId();
            $success = $this->todoManager->restore($id, $userId);

            if (!$success) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Failed to restore todo',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $restoredTodo = $this->todoManager->findById($id);

            return new JsonResponse([
                'success' => true,
                'data' => $restoredTodo->toArray(),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Bulk update todos
     */
    #[Route('/todos/bulk-update', name: 'bulk_update', methods: ['POST'])]
    public function bulkUpdate(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(TodoPermission::Manage->value);

        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Invalid JSON body',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!isset($data['ids']) || !is_array($data['ids'])) {
            return new JsonResponse([
                'success' => false,
                'error' => 'IDs array is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!isset($data['data']) || !is_array($data['data'])) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Data object is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $ids = array_filter(array_map('intval', $data['ids']), fn(int $id) => $id > 0);

        try {
            $userId = $this->getUserId();
            $count = $this->todoManager->bulkUpdate(array_values($ids), $data['data'], $userId);

            return new JsonResponse([
                'success' => true,
                'updated' => $count,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Bulk delete todos
     */
    #[Route('/todos/bulk-delete', name: 'bulk_delete', methods: ['POST'])]
    public function bulkDelete(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(TodoPermission::Manage->value);

        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Invalid JSON body',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!isset($data['ids']) || !is_array($data['ids'])) {
            return new JsonResponse([
                'success' => false,
                'error' => 'IDs array is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $ids = array_filter(array_map('intval', $data['ids']), fn(int $id) => $id > 0);

        try {
            $userId = $this->getUserId();
            $count = $this->todoManager->bulkDelete(array_values($ids), $userId);

            return new JsonResponse([
                'success' => true,
                'deleted' => $count,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Hard delete a todo (permanent, Admin only)
     */
    #[Route('/todos/{id}/hard-delete', name: 'hard_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function hardDelete(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(TodoPermission::Admin->value);
        $todo = $this->todoManager->findById($id, includeDeleted: true);

        if (!$todo) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Todo not found',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $userId = $this->getUserId();
            $success = $this->todoManager->hardDelete($id, $userId);

            if (!$success) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Failed to permanently delete todo',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return new JsonResponse([
                'success' => true,
                'message' => 'Todo permanently deleted',
            ]);
        } catch (\RuntimeException $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get current user ID
     */
    private function getUserId(): ?int
    {
        $user = $this->getUser();
        return $user ? $user->getId() : null;
    }
}
