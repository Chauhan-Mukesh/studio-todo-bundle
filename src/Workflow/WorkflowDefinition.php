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
 * Built-in workflow definition for the todo_workflow state machine.
 *
 * Defines the default states and transitions used when no custom Pimcore/Symfony
 * Workflow configuration is supplied.  All members are static so the class acts
 * as a pure constants/utility holder and does not need to be instantiated.
 */
final class WorkflowDefinition
{
    public const string WORKFLOW_NAME = 'todo_workflow';

    /** Name of the terminal state that marks a todo as done. */
    public const string COMPLETED_STATE = 'completed';

    /**
     * Workflow states: name => metadata
     *
     * @var array<string, array{label: string, initial: bool}>
     */
    public const array STATES = [
        'open'           => ['label' => 'Open',           'initial' => true],
        'in_progress'    => ['label' => 'In Progress',    'initial' => false],
        'on_hold'        => ['label' => 'On Hold',        'initial' => false],
        'pending_review' => ['label' => 'Pending Review', 'initial' => false],
        'completed'      => ['label' => 'Completed',      'initial' => false],
        'cancelled'      => ['label' => 'Cancelled',      'initial' => false],
    ];

    /**
     * Workflow transitions: name => definition
     *
     * @var array<string, array{froms: string[], to: string, label: string}>
     */
    public const array TRANSITIONS = [
        'start' => [
            'froms' => ['open'],
            'to'    => 'in_progress',
            'label' => 'Start',
        ],
        'hold' => [
            'froms' => ['in_progress'],
            'to'    => 'on_hold',
            'label' => 'Put on Hold',
        ],
        'resume' => [
            'froms' => ['on_hold'],
            'to'    => 'in_progress',
            'label' => 'Resume',
        ],
        'request_review' => [
            'froms' => ['in_progress'],
            'to'    => 'pending_review',
            'label' => 'Request Review',
        ],
        'approve' => [
            'froms' => ['pending_review'],
            'to'    => 'completed',
            'label' => 'Approve',
        ],
        'reject' => [
            'froms' => ['pending_review'],
            'to'    => 'in_progress',
            'label' => 'Reject',
        ],
        'cancel' => [
            'froms' => ['open', 'in_progress', 'on_hold', 'pending_review'],
            'to'    => 'cancelled',
            'label' => 'Cancel',
        ],
        'reopen' => [
            'froms' => ['completed', 'cancelled'],
            'to'    => 'open',
            'label' => 'Reopen',
        ],
    ];

    /**
     * Maps a workflow state to the corresponding TodoStatus enum value.
     *
     * @var array<string, string>
     */
    public const array STATUS_MAP = [
        'open'           => 'open',
        'in_progress'    => 'in_progress',
        'on_hold'        => 'on_hold',
        'pending_review' => 'in_progress',
        'completed'      => 'completed',
        'cancelled'      => 'cancelled',
    ];

    /**
     * Return the name of the initial state.
     */
    public static function getInitialState(): string
    {
        foreach (self::STATES as $name => $meta) {
            if ($meta['initial']) {
                return $name;
            }
        }

        return 'open';
    }

    /**
     * Return all transition names available from a given state.
     *
     * @return string[]
     */
    public static function getAvailableTransitions(string $fromState): array
    {
        $available = [];
        foreach (self::TRANSITIONS as $name => $definition) {
            if (in_array($fromState, $definition['froms'], true)) {
                $available[] = $name;
            }
        }

        return $available;
    }

    /**
     * Check whether a named transition is valid from the given state.
     */
    public static function canTransition(string $fromState, string $transitionName): bool
    {
        if (!isset(self::TRANSITIONS[$transitionName])) {
            return false;
        }

        return in_array($fromState, self::TRANSITIONS[$transitionName]['froms'], true);
    }

    /**
     * Return the target state for a transition, or null if the transition is unknown.
     */
    public static function getTargetState(string $transitionName): ?string
    {
        return self::TRANSITIONS[$transitionName]['to'] ?? null;
    }

    /** Private constructor – class must not be instantiated. */
    private function __construct()
    {
    }
}
