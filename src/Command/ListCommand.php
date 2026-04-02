<?php

/**
 * Studio Todo Bundle for Pimcore 12+
 *
 * @license MIT
 * @author Mukesh Chauhan
 */

declare(strict_types=1);

namespace ChauhanMukesh\StudioTodoBundle\Command;

use ChauhanMukesh\StudioTodoBundle\Repository\TodoRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * List Command - List todos via CLI
 */
#[AsCommand(
    name: 'studio-todo:list',
    description: 'List todos with optional filtering'
)]
class ListCommand extends Command
{
    public function __construct(
        private readonly TodoRepository $repository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'Filter by status')
            ->addOption('priority', null, InputOption::VALUE_REQUIRED, 'Filter by priority')
            ->addOption('assigned-to', null, InputOption::VALUE_REQUIRED, 'Filter by assigned user ID')
            ->addOption('category', null, InputOption::VALUE_REQUIRED, 'Filter by category')
            ->addOption('overdue', null, InputOption::VALUE_NONE, 'Show only overdue todos')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Limit number of results', 20);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filters = [];

        // Apply filters
        if ($input->getOption('status')) {
            $filters['status'] = $input->getOption('status');
        }

        if ($input->getOption('priority')) {
            $filters['priority'] = $input->getOption('priority');
        }

        if ($input->getOption('assigned-to')) {
            $filters['assigned_to_user_id'] = (int) $input->getOption('assigned-to');
        }

        if ($input->getOption('category')) {
            $filters['category'] = $input->getOption('category');
        }

        if ($input->getOption('overdue')) {
            $filters['overdue'] = true;
        }

        $limit = (int) $input->getOption('limit');

        // Fetch todos
        $todos = $this->repository->findAll($filters, $limit);
        $total = $this->repository->count($filters);

        if (empty($todos)) {
            $io->info('No todos found matching the criteria.');
            return Command::SUCCESS;
        }

        // Display results
        $io->title('Todos List');
        $io->text(sprintf('Showing %d of %d todos', count($todos), $total));

        $table = new Table($output);
        $table->setHeaders(['ID', 'Title', 'Status', 'Priority', 'Assigned To', 'Due Date', 'Created At']);

        foreach ($todos as $todo) {
            $table->addRow([
                $todo->id,
                $this->truncate($todo->title, 40),
                $todo->status->value,
                $todo->priority->value,
                $todo->assignedToUserId ?? '-',
                $todo->dueDate?->format('Y-m-d') ?? '-',
                $todo->createdAt->format('Y-m-d H:i'),
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }

    private function truncate(string $text, int $length): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length - 3) . '...';
    }
}
