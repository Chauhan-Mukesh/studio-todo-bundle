<?php

/**
 * Studio Todo Bundle for Pimcore 12+
 *
 * @license MIT
 * @author Mukesh Chauhan
 */

declare(strict_types=1);

namespace ChauhanMukesh\StudioTodoBundle\Workflow;

/**
 * TodoWorkflowSubject
 *
 * Mutable wrapper used as the Symfony Workflow subject for a todo item.
 *
 * Symfony's Workflow component requires a mutable object whose state it can
 * read and write via a MarkingStore.  Because TodoItem is an immutable readonly
 * value object, we cannot use it directly.  This lightweight class holds only
 * the todo ID and its current workflow_state string.
 *
 * When Symfony's MethodMarkingStore is configured with
 *   type: method, property: workflowState
 * it will read/write the public $workflowState property directly.
 *
 * After Symfony Workflow calls apply(), this object will hold the new state;
 * the caller is responsible for persisting it back to the database.
 *
 * Sample framework.yaml (see Resources/doc/config/workflow.yaml):
 *
 *   marking_store:
 *     type: method
 *     property: workflowState
 *   supports:
 *     - ChauhanMukesh\StudioTodoBundle\Workflow\TodoWorkflowSubject
 */
class TodoWorkflowSubject
{
    /**
     * The current workflow state.
     * Public (non-readonly) so Symfony's MethodMarkingStore can write to it.
     */
    public string $workflowState;

    public function __construct(
        public readonly int $todoId,
        string $currentState = ''
    ) {
        $this->workflowState = $currentState !== '' ? $currentState : WorkflowDefinition::getInitialState();
    }

    public function getTodoId(): int
    {
        return $this->todoId;
    }
}
