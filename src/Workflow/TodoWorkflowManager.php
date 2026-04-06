<?php

/**
 * Studio Todo Bundle for Pimcore 12+
 *
 * @license MIT
 * @author Mukesh Chauhan
 */

declare(strict_types=1);

namespace ChauhanMukesh\StudioTodoBundle\Workflow;

use ChauhanMukesh\StudioTodoBundle\Event\TodoEvent;
use ChauhanMukesh\StudioTodoBundle\Event\TodoEvents;
use ChauhanMukesh\StudioTodoBundle\Enum\TodoStatus;
use ChauhanMukesh\StudioTodoBundle\Model\TodoItem;
use ChauhanMukesh\StudioTodoBundle\Repository\TodoRepository;
use ChauhanMukesh\StudioTodoBundle\Service\AuditLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * TodoWorkflowManager
 *
 * Core workflow service for the Studio Todo Bundle.
 * Manages Pimcore workflow state for todo items using the built-in state machine
 * (WorkflowDefinition).  Handles transition validation, DB persistence,
 * audit logging, status synchronisation and event dispatching.
 *
 * This service is self-contained and does not require the Symfony Workflow
 * component to be configured in the host application, making the bundle
 * compatible with both Pimcore 11 and 12 out of the box.
 */
class TodoWorkflowManager
{
    private readonly bool $enabled;
    private readonly string $workflowName;
    private readonly bool $syncStatus;

    /**
     * @param array<string, mixed> $config  Value of the `studio_todo.workflow` parameter
     */
    public function __construct(
        private readonly TodoRepository $repository,
        private readonly AuditLogger $auditLogger,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly array $config
    ) {
        $this->enabled      = (bool) ($config['enabled']          ?? false);
        $this->workflowName = (string) ($config['default_workflow'] ?? WorkflowDefinition::WORKFLOW_NAME);
        $this->syncStatus   = (bool) ($config['sync_status']      ?? true);
    }

    // -------------------------------------------------------------------------
    // Configuration helpers
    // -------------------------------------------------------------------------

    /**
     * Check whether workflow integration is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Return the configured workflow name.
     */
    public function getWorkflowName(): string
    {
        return $this->workflowName;
    }

    /**
     * Return the list of Symfony roles allowed to apply workflow transitions.
     * An empty array means any authenticated user may apply transitions.
     *
     * @return string[]
     */
    public function getAllowedRoles(): array
    {
        return (array) ($this->config['allowed_roles'] ?? []);
    }

    // -------------------------------------------------------------------------
    // State queries
    // -------------------------------------------------------------------------

    /**
     * Return the current workflow state for a todo.
     * Falls back to the initial state when no state has been assigned yet.
     */
    public function getCurrentState(TodoItem $todo): string
    {
        return $todo->workflowState ?? WorkflowDefinition::getInitialState();
    }

    /**
     * Return all transitions that can currently be applied to a todo, keyed by
     * transition name.
     *
     * @return array<string, array{name: string, label: string, from: string, to: string, to_label: string}>
     */
    public function getAvailableTransitions(TodoItem $todo): array
    {
        $currentState    = $this->getCurrentState($todo);
        $transitionNames = WorkflowDefinition::getAvailableTransitions($currentState);

        $result = [];
        foreach ($transitionNames as $name) {
            $definition = WorkflowDefinition::TRANSITIONS[$name];
            $result[$name] = [
                'name'     => $name,
                'label'    => $definition['label'],
                'from'     => $currentState,
                'to'       => $definition['to'],
                'to_label' => WorkflowDefinition::STATES[$definition['to']]['label'] ?? $definition['to'],
            ];
        }

        return $result;
    }

    /**
     * Check whether a specific transition can be applied to a todo.
     */
    public function canApply(TodoItem $todo, string $transitionName): bool
    {
        return WorkflowDefinition::canTransition($this->getCurrentState($todo), $transitionName);
    }

    // -------------------------------------------------------------------------
    // State mutations
    // -------------------------------------------------------------------------

    /**
     * Apply a workflow transition to a todo.
     *
     * - Validates that the transition is allowed from the current state.
     * - Persists the new workflow_state (and optionally status) to the database.
     * - Writes a workflow_transition audit log entry.
     * - Dispatches a TodoEvents::WORKFLOW_TRANSITION event.
     *
     * @throws \InvalidArgumentException when the transition is not allowed.
     * @throws \RuntimeException         when the todo cannot be reloaded after update.
     */
    public function applyTransition(TodoItem $todo, string $transitionName, ?int $userId = null): TodoItem
    {
        if (!$this->canApply($todo, $transitionName)) {
            throw new \InvalidArgumentException(sprintf(
                'Transition "%s" is not allowed from state "%s" for todo #%d.',
                $transitionName,
                $this->getCurrentState($todo),
                $todo->id
            ));
        }

        $targetState = WorkflowDefinition::getTargetState($transitionName);
        if ($targetState === null) {
            throw new \InvalidArgumentException(sprintf('Unknown transition "%s".', $transitionName));
        }

        $previousState = $this->getCurrentState($todo);

        $updateData = [
            'workflow_state'       => $targetState,
            'updated_by_user_id'   => $userId,
        ];

        // Optionally keep TodoStatus in sync with the workflow state.
        if ($this->syncStatus) {
            $mappedStatusValue = WorkflowDefinition::STATUS_MAP[$targetState] ?? null;
            if ($mappedStatusValue !== null) {
                $mappedStatus = TodoStatus::tryFrom($mappedStatusValue);
                if ($mappedStatus !== null && $mappedStatus !== $todo->status) {
                    $updateData['status'] = $mappedStatus->value;
                    if ($targetState === WorkflowDefinition::COMPLETED_STATE) {
                        $updateData['completed_at'] = new \DateTimeImmutable();
                    }
                }
            }
        }

        $this->repository->update($todo->id, $updateData);

        // Audit log
        $this->auditLogger->logCustom(
            todoId: $todo->id,
            action: 'workflow_transition',
            fieldName: 'workflow_state',
            oldValue: $previousState,
            newValue: $targetState,
            userId: $userId
        );

        // Reload to get the freshly persisted state
        $updatedTodo = $this->repository->findById($todo->id);
        if ($updatedTodo === null) {
            throw new \RuntimeException(sprintf('Todo #%d not found after workflow transition.', $todo->id));
        }

        // Dispatch workflow event
        $event = new TodoEvent($updatedTodo, $todo);
        $this->eventDispatcher->dispatch($event, TodoEvents::WORKFLOW_TRANSITION);

        return $updatedTodo;
    }

    /**
     * Initialise the workflow state for a newly created todo.
     *
     * Sets workflow_state to the initial state and logs the action.
     * Called by TodoManager::create() when workflow is enabled.
     */
    public function initializeState(int $todoId, ?int $userId = null): void
    {
        $initialState = WorkflowDefinition::getInitialState();

        $this->repository->update($todoId, ['workflow_state' => $initialState]);

        $this->auditLogger->logCustom(
            todoId: $todoId,
            action: 'workflow_init',
            fieldName: 'workflow_state',
            oldValue: null,
            newValue: $initialState,
            userId: $userId
        );
    }

    /**
     * Return the complete workflow definition as a serialisable array.
     *
     * @return array<string, mixed>
     */
    public function getDefinition(): array
    {
        return [
            'name'        => $this->workflowName,
            'states'      => WorkflowDefinition::STATES,
            'transitions' => WorkflowDefinition::TRANSITIONS,
            'status_map'  => WorkflowDefinition::STATUS_MAP,
        ];
    }
}
