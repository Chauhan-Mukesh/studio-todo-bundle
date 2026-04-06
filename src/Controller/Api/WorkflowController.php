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
use ChauhanMukesh\StudioTodoBundle\Workflow\TodoWorkflowManager;
use Pimcore\Model\User as PimcoreUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Workflow Controller - REST API for todo workflow operations
 *
 * Exposes three endpoints:
 *   GET  /todos/{id}/workflow           – current state + available transitions
 *   POST /todos/{id}/workflow/transition – apply a named transition
 *   GET  /workflow/definition           – full state-machine definition
 */
#[Route('/pimcore-studio/api/studio-todo', name: 'studio_todo_workflow_')]
class WorkflowController extends AbstractController
{
    public function __construct(
        private readonly TodoManager $todoManager,
        private readonly TodoWorkflowManager $workflowManager
    ) {
    }

    /**
     * Get the current workflow state and available transitions for a todo.
     *
     * Response shape:
     * {
     *   "success": true,
     *   "data": {
     *     "workflow_name": "todo_workflow",
     *     "current_state": "open",
     *     "available_transitions": [{ "name": "start", "label": "Start", ... }]
     *   }
     * }
     */
    #[Route('/todos/{id}/workflow', name: 'info', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function info(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(TodoPermission::View->value);

        if (!$this->workflowManager->isEnabled()) {
            return $this->workflowDisabled();
        }

        $todo = $this->todoManager->findById($id);
        if (!$todo) {
            return $this->notFound();
        }

        return new JsonResponse([
            'success' => true,
            'data'    => [
                'workflow_name'         => $this->workflowManager->getWorkflowName(),
                'current_state'         => $this->workflowManager->getCurrentState($todo),
                'available_transitions' => array_values($this->workflowManager->getAvailableTransitions($todo)),
            ],
        ]);
    }

    /**
     * Apply a workflow transition to a todo.
     *
     * Request body: { "transition": "start" }
     *
     * Response shape:
     * {
     *   "success": true,
     *   "data": {
     *     "todo": { ...todo fields... },
     *     "current_state": "in_progress",
     *     "available_transitions": [...]
     *   }
     * }
     */
    #[Route('/todos/{id}/workflow/transition', name: 'transition', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function transition(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(TodoPermission::Manage->value);

        if (!$this->workflowManager->isEnabled()) {
            return $this->workflowDisabled();
        }

        $todo = $this->todoManager->findById($id);
        if (!$todo) {
            return $this->notFound();
        }

        $body = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonResponse([
                'success' => false,
                'error'   => 'Invalid JSON body: ' . json_last_error_msg(),
            ], Response::HTTP_BAD_REQUEST);
        }
        if (!is_array($body) || empty($body['transition'])) {
            return new JsonResponse([
                'success' => false,
                'error'   => 'Request body must contain a non-empty "transition" field.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $transitionName = (string) $body['transition'];

        // Enforce allowed_roles when configured
        $allowedRoles = $this->workflowManager->getAllowedRoles();
        if (!empty($allowedRoles)) {
            $hasRole = false;
            foreach ($allowedRoles as $role) {
                if ($this->isGranted($role)) {
                    $hasRole = true;
                    break;
                }
            }
            if (!$hasRole) {
                return new JsonResponse([
                    'success' => false,
                    'error'   => 'You do not have permission to apply workflow transitions.',
                ], Response::HTTP_FORBIDDEN);
            }
        }

        try {
            $userId      = $this->getUserId();
            $updatedTodo = $this->workflowManager->applyTransition($todo, $transitionName, $userId);

            return new JsonResponse([
                'success' => true,
                'data'    => [
                    'todo'                  => $updatedTodo->toArray(),
                    'current_state'         => $this->workflowManager->getCurrentState($updatedTodo),
                    'available_transitions' => array_values($this->workflowManager->getAvailableTransitions($updatedTodo)),
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse([
                'success' => false,
                'error'   => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\RuntimeException $e) {
            return new JsonResponse([
                'success' => false,
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Return the full workflow definition (states, transitions, status mapping).
     *
     * Response shape:
     * {
     *   "success": true,
     *   "data": { "name": "todo_workflow", "states": {...}, "transitions": {...}, "status_map": {...} }
     * }
     */
    #[Route('/workflow/definition', name: 'definition', methods: ['GET'])]
    public function definition(): JsonResponse
    {
        $this->denyAccessUnlessGranted(TodoPermission::View->value);

        if (!$this->workflowManager->isEnabled()) {
            return $this->workflowDisabled();
        }

        return new JsonResponse([
            'success' => true,
            'data'    => $this->workflowManager->getDefinition(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function workflowDisabled(): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'error'   => 'Workflow integration is not enabled. Set studio_todo.workflow.enabled: true to activate it.',
        ], Response::HTTP_SERVICE_UNAVAILABLE);
    }

    private function notFound(): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'error'   => 'Todo not found.',
        ], Response::HTTP_NOT_FOUND);
    }

    private function getUserId(): ?int
    {
        $user = $this->getUser();
        // @phpstan-ignore-next-line instanceof check works at runtime (Pimcore User implements UserInterface)
        if ($user instanceof PimcoreUser) {
            return $user->getId();
        }

        return null;
    }
}
