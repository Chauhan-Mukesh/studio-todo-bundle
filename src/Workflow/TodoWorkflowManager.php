<?php

/**
 * Studio Todo Bundle for Pimcore 12+
 *
 * @license MIT
 * @author Mukesh Chauhan
 */

declare(strict_types=1);

namespace ChauhanMukesh\StudioTodoBundle\Workflow;

use ChauhanMukesh\StudioTodoBundle\Enum\TodoStatus;
use ChauhanMukesh\StudioTodoBundle\Event\TodoEvent;
use ChauhanMukesh\StudioTodoBundle\Event\TodoEvents;
use ChauhanMukesh\StudioTodoBundle\Model\TodoItem;
use ChauhanMukesh\StudioTodoBundle\Repository\TodoRepository;
use ChauhanMukesh\StudioTodoBundle\Service\AuditLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * TodoWorkflowManager
 *
 * Core workflow service for the Studio Todo Bundle.
 *
 * Supports two modes that are selected automatically:
 *
 * 1. **Symfony Workflow mode** (preferred):
 *    When a `WorkflowInterface` service named `state_machine.todo_workflow` is
 *    registered in the DI container (i.e. the host application has declared the
 *    workflow under `framework.workflows.todo_workflow` / Pimcore's workflow
 *    config), it is injected here and all transition logic is delegated to it.
 *    This gives full Symfony Workflow features: guards, events, audit metadata
 *    and any custom places/transitions defined in the application config.
 *
 * 2. **Built-in state machine** (fallback):
 *    When no Symfony Workflow is configured, the bundle's own static
 *    WorkflowDefinition class handles all validation and state resolution.
 *
 * Either way, this service:
 * - Persists workflow_state (and optionally status) to the database.
 * - Writes a workflow_transition audit log entry.
 * - Dispatches a TodoEvents::WORKFLOW_TRANSITION domain event.
 */
class TodoWorkflowManager
{
    private readonly bool $enabled;
    private readonly string $workflowName;
    private readonly bool $syncStatus;

    /**
     * @param array<string, mixed> $config         Value of the `studio_todo.workflow` parameter
     * @param WorkflowInterface|null $symfonyWorkflow  Injected as `@?state_machine.todo_workflow`.
     *                                               Null when the Symfony workflow is not configured.
     */
    public function __construct(
        private readonly TodoRepository $repository,
        private readonly AuditLogger $auditLogger,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly array $config,
        private readonly ?WorkflowInterface $symfonyWorkflow = null
    ) {
        $this->enabled      = (bool) ($config['enabled']           ?? false);
        $this->workflowName = (string) ($config['default_workflow'] ?? WorkflowDefinition::WORKFLOW_NAME);
        $this->syncStatus   = (bool) ($config['sync_status']       ?? true);
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

    /**
     * Return true when a Symfony Workflow service has been injected.
     */
    public function usesSymfonyWorkflow(): bool
    {
        return $this->symfonyWorkflow !== null;
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
     * When a Symfony Workflow is registered, `getEnabledTransitions()` is used
     * so that any guards defined in the workflow config are respected.
     * Transition labels are read from Symfony Workflow metadata when available.
     *
     * @return array<string, array{name: string, label: string, from: string, to: string, to_label: string}>
     */
    public function getAvailableTransitions(TodoItem $todo): array
    {
        $currentState = $this->getCurrentState($todo);

        if ($this->symfonyWorkflow !== null) {
            return $this->getAvailableTransitionsViaSymfony($todo, $currentState);
        }

        return $this->getAvailableTransitionsViaBuiltin($currentState);
    }

    /**
     * Check whether a specific transition can be applied to a todo.
     */
    public function canApply(TodoItem $todo, string $transitionName): bool
    {
        if ($this->symfonyWorkflow !== null) {
            $subject = $this->makeSubject($todo);
            return $this->symfonyWorkflow->can($subject, $transitionName);
        }

        return WorkflowDefinition::canTransition($this->getCurrentState($todo), $transitionName);
    }

    // -------------------------------------------------------------------------
    // State mutations
    // -------------------------------------------------------------------------

    /**
     * Apply a workflow transition to a todo.
     *
     * Delegates state validation to the Symfony Workflow component when
     * registered (honouring guards and event listeners defined there),
     * or to the built-in WorkflowDefinition otherwise.
     *
     * In both cases:
     * - Persists the new workflow_state (and optionally status/completed_at).
     * - Writes a workflow_transition audit log entry.
     * - Dispatches TodoEvents::WORKFLOW_TRANSITION.
     *
     * @throws \InvalidArgumentException when the transition is not allowed.
     * @throws \RuntimeException         when the todo cannot be reloaded after update.
     */
    public function applyTransition(TodoItem $todo, string $transitionName, ?int $userId = null): TodoItem
    {
        $previousState = $this->getCurrentState($todo);

        if ($this->symfonyWorkflow !== null) {
            $targetState = $this->applyViaSymfony($todo, $transitionName, $userId);
        } else {
            $targetState = $this->applyViaBuiltin($todo, $transitionName);
        }

        $updateData = [
            'workflow_state'     => $targetState,
            'updated_by_user_id' => $userId,
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

        // Dispatch our domain event (separate from Symfony Workflow events)
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

    // -------------------------------------------------------------------------
    // Metadata
    // -------------------------------------------------------------------------

    /**
     * Return the complete workflow definition as a serialisable array.
     *
     * The base definition always comes from WorkflowDefinition constants
     * (mirrors the sample config in Resources/doc/config/workflow.yaml).
     * A `driver` key indicates whether Symfony Workflow is in use so that
     * API consumers know which component is enforcing the state machine.
     *
     * @return array<string, mixed>
     */
    public function getDefinition(): array
    {
        return [
            'name'        => $this->workflowName,
            'driver'      => $this->symfonyWorkflow !== null ? 'symfony_workflow' : 'builtin',
            'states'      => WorkflowDefinition::STATES,
            'transitions' => WorkflowDefinition::TRANSITIONS,
            'status_map'  => WorkflowDefinition::STATUS_MAP,
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Build a TodoWorkflowSubject from a TodoItem for use with Symfony Workflow.
     */
    private function makeSubject(TodoItem $todo): TodoWorkflowSubject
    {
        return new TodoWorkflowSubject($todo->id, $this->getCurrentState($todo));
    }

    /**
     * Apply a transition via Symfony WorkflowInterface.
     *
     * Symfony's apply() writes the new state back to the subject via the
     * MarkingStore (which writes to $subject->workflowState).
     *
     * @throws \InvalidArgumentException when the transition is not allowed.
     */
    private function applyViaSymfony(TodoItem $todo, string $transitionName, ?int $userId): string
    {
        $subject = $this->makeSubject($todo);

        if (!$this->symfonyWorkflow->can($subject, $transitionName)) {
            throw new \InvalidArgumentException(sprintf(
                'Transition "%s" is not allowed from state "%s" for todo #%d.',
                $transitionName,
                $subject->workflowState,
                $todo->id
            ));
        }

        // Symfony apply() calls MarkingStore::setMarking() which writes the new
        // state into $subject->workflowState via MethodMarkingStore.
        $this->symfonyWorkflow->apply($subject, $transitionName, ['user_id' => $userId]);

        return $subject->workflowState;
    }

    /**
     * Apply a transition via the built-in WorkflowDefinition state machine.
     *
     * @throws \InvalidArgumentException when the transition is not allowed.
     */
    private function applyViaBuiltin(TodoItem $todo, string $transitionName): string
    {
        if (!WorkflowDefinition::canTransition($this->getCurrentState($todo), $transitionName)) {
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

        return $targetState;
    }

    /**
     * Resolve available transitions via Symfony WorkflowInterface.
     *
     * Uses `getMetadataStore()` to read optional `label` metadata from the
     * workflow YAML configuration.
     *
     * @return array<string, array{name: string, label: string, from: string, to: string, to_label: string}>
     */
    private function getAvailableTransitionsViaSymfony(TodoItem $todo, string $currentState): array
    {
        $subject        = $this->makeSubject($todo);
        $transitions    = $this->symfonyWorkflow->getEnabledTransitions($subject);
        $metadataStore  = $this->symfonyWorkflow->getMetadataStore();

        $result = [];
        foreach ($transitions as $transition) {
            $name        = $transition->getName();
            $transitionMeta = $metadataStore->getTransitionMetadata($transition);
            $to          = $transition->getTos()[0] ?? '';
            $placeMeta   = $metadataStore->getPlaceMetadata($to);

            $result[$name] = [
                'name'     => $name,
                'label'    => $transitionMeta['label'] ?? ucwords(str_replace('_', ' ', $name)),
                'from'     => $currentState,
                'to'       => $to,
                'to_label' => $placeMeta['label'] ?? (WorkflowDefinition::STATES[$to]['label'] ?? ucwords(str_replace('_', ' ', $to))),
            ];
        }

        return $result;
    }

    /**
     * Resolve available transitions via the built-in WorkflowDefinition.
     *
     * @return array<string, array{name: string, label: string, from: string, to: string, to_label: string}>
     */
    private function getAvailableTransitionsViaBuiltin(string $currentState): array
    {
        $transitionNames = WorkflowDefinition::getAvailableTransitions($currentState);

        $result = [];
        foreach ($transitionNames as $name) {
            $definition = WorkflowDefinition::TRANSITIONS[$name];
            $to         = $definition['to'];
            $result[$name] = [
                'name'     => $name,
                'label'    => $definition['label'],
                'from'     => $currentState,
                'to'       => $to,
                'to_label' => WorkflowDefinition::STATES[$to]['label'] ?? $to,
            ];
        }

        return $result;
    }
}
