<?php

/**
 * Studio Todo Bundle for Pimcore 12+
 *
 * @license MIT
 * @author Mukesh Chauhan
 */

declare(strict_types=1);

namespace ChauhanMukesh\StudioTodoBundle\Command;

use ChauhanMukesh\StudioTodoBundle\Service\StatisticsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Stats Command - Display todo statistics
 */
#[AsCommand(
    name: 'studio-todo:stats',
    description: 'Display todo statistics'
)]
class StatsCommand extends Command
{
    public function __construct(
        private readonly StatisticsService $statsService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Todo Statistics');

        try {
            // Overall statistics
            $overall = $this->statsService->getOverallStatistics();

            $io->section('Overall Statistics');
            $io->table(
                ['Metric', 'Count', 'Percentage'],
                [
                    ['Total', $overall['total'], '100%'],
                    ['Open', $overall['open'], sprintf('%.2f%%', $overall['open_percentage'] ?? 0)],
                    ['In Progress', $overall['in_progress'], sprintf('%.2f%%', $overall['in_progress_percentage'] ?? 0)],
                    ['Completed', $overall['completed'], sprintf('%.2f%%', $overall['completed_percentage'] ?? 0)],
                    ['Cancelled', $overall['cancelled'], '-'],
                    ['On Hold', $overall['on_hold'], '-'],
                    ['Overdue', $overall['overdue'], sprintf('%.2f%%', $overall['overdue_percentage'] ?? 0)],
                ]
            );

            // Statistics by priority
            $byPriority = $this->statsService->getStatisticsByPriority();

            $io->section('Statistics by Priority');
            $table = new Table($output);
            $table->setHeaders(['Priority', 'Count']);
            foreach ($byPriority as $stat) {
                $table->addRow([$stat['priority'], $stat['count']]);
            }
            $table->render();

            // Statistics by user
            $byUser = $this->statsService->getStatisticsByUser();

            if (!empty($byUser)) {
                $io->section('Statistics by User (Top 10)');
                $table = new Table($output);
                $table->setHeaders(['User ID', 'Total', 'Open', 'In Progress', 'Completed']);
                foreach (array_slice($byUser, 0, 10) as $stat) {
                    $table->addRow([
                        $stat['assigned_to_user_id'],
                        $stat['total'],
                        $stat['open'],
                        $stat['in_progress'],
                        $stat['completed'],
                    ]);
                }
                $table->render();
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Failed to fetch statistics: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }
}
