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
use ChauhanMukesh\StudioTodoBundle\Service\TodoManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Cleanup Command - Cleanup old completed todos
 */
#[AsCommand(
    name: 'studio-todo:cleanup',
    description: 'Cleanup old completed todos'
)]
class CleanupCommand extends Command
{
    public function __construct(
        private readonly TodoRepository $repository,
        private readonly TodoManager $todoManager,
        private readonly array $config
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Delete todos completed more than X days ago', 90)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be deleted without actually deleting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $days = (int) $input->getOption('days');
        $dryRun = $input->getOption('dry-run');

        $io->title('Todo Cleanup');

        if ($dryRun) {
            $io->note('Running in DRY RUN mode - no todos will be deleted');
        }

        $io->text(sprintf('Looking for todos completed more than %d days ago...', $days));

        try {
            $count = $this->todoManager->cleanup($days, $dryRun);

            if ($dryRun) {
                $io->success(sprintf('Found %d todos that would be deleted', $count));
            } else {
                $io->success(sprintf('Successfully deleted %d old todos', $count));
            }

            // Also cleanup soft-deleted todos if configured
            if ($this->config['enabled'] ?? true) {
                $autoCleanupDays = $this->config['auto_cleanup_days'] ?? 0;

                if ($autoCleanupDays > 0) {
                    $cutoffDate = new \DateTimeImmutable("-{$autoCleanupDays} days");
                    $softDeletedTodos = $this->repository->findSoftDeletedBefore($cutoffDate);

                    $hardDeleteCount = 0;
                    foreach ($softDeletedTodos as $todo) {
                        if (!$dryRun) {
                            $this->todoManager->hardDelete($todo->id);
                        }
                        $hardDeleteCount++;
                    }

                    if ($hardDeleteCount > 0) {
                        if ($dryRun) {
                            $io->info(sprintf('Found %d soft-deleted todos that would be permanently removed', $hardDeleteCount));
                        } else {
                            $io->info(sprintf('Permanently removed %d soft-deleted todos older than %d days', $hardDeleteCount, $autoCleanupDays));
                        }
                    }
                }
            }

            return Command::SUCCESS;
        } catch (\RuntimeException | \InvalidArgumentException $e) {
            $io->error(sprintf('Cleanup failed: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }
}
