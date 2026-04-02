<?php

/**
 * Studio Todo Bundle for Pimcore 12+
 *
 * @license MIT
 * @author Mukesh Chauhan
 */

declare(strict_types=1);

namespace ChauhanMukesh\StudioTodoBundle\Command;

use ChauhanMukesh\StudioTodoBundle\Service\TodoManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Create Command - Create todos via CLI
 */
#[AsCommand(
    name: 'studio-todo:create',
    description: 'Create a new todo'
)]
class CreateCommand extends Command
{
    public function __construct(
        private readonly TodoManager $todoManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('title', 't', InputOption::VALUE_REQUIRED, 'Todo title')
            ->addOption('description', 'd', InputOption::VALUE_OPTIONAL, 'Todo description')
            ->addOption('status', 's', InputOption::VALUE_OPTIONAL, 'Todo status', 'open')
            ->addOption('priority', 'p', InputOption::VALUE_OPTIONAL, 'Todo priority', 'medium')
            ->addOption('assigned-to', 'a', InputOption::VALUE_OPTIONAL, 'Assigned user ID')
            ->addOption('due-date', null, InputOption::VALUE_OPTIONAL, 'Due date (e.g., "+7 days", "2026-04-10")')
            ->addOption('category', 'c', InputOption::VALUE_OPTIONAL, 'Category');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Get title (required)
        $title = $input->getOption('title');
        if (!$title) {
            $title = $io->ask('Todo title', null, function ($value) {
                if (empty($value)) {
                    throw new \RuntimeException('Title cannot be empty');
                }
                return $value;
            });
        }

        // Prepare data
        $data = [
            'title' => $title,
            'description' => $input->getOption('description'),
            'status' => $input->getOption('status'),
            'priority' => $input->getOption('priority'),
            'category' => $input->getOption('category'),
        ];

        if ($input->getOption('assigned-to')) {
            $data['assigned_to_user_id'] = (int) $input->getOption('assigned-to');
        }

        if ($input->getOption('due-date')) {
            $data['due_date'] = new \DateTimeImmutable($input->getOption('due-date'));
        }

        try {
            $todoId = $this->todoManager->create($data);
            $todo = $this->todoManager->findById($todoId);

            $io->success(sprintf('Todo created successfully with ID: %d', $todoId));

            // Display details
            $io->table(
                ['Field', 'Value'],
                [
                    ['ID', $todo->id],
                    ['Title', $todo->title],
                    ['Description', $todo->description ?? '-'],
                    ['Status', $todo->status->value],
                    ['Priority', $todo->priority->value],
                    ['Category', $todo->category ?? '-'],
                    ['Assigned To', $todo->assignedToUserId ?? '-'],
                    ['Due Date', $todo->dueDate?->format('Y-m-d H:i') ?? '-'],
                    ['Created At', $todo->createdAt->format('Y-m-d H:i')],
                ]
            );

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Failed to create todo: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }
}
