<?php

namespace App\Command;

use App\Service\EmployeeService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync-employees',
    description: 'Sync all pending employees to TrackTik API',
)]
class SyncEmployeesCommand extends Command
{
    public function __construct(
        private readonly EmployeeService $employeeService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force sync all employees (including already synced)')
            ->setHelp('This command syncs all pending employees to the TrackTik API');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');

        $io->title('TrackTik Employee Sync');

        try {
            if ($force) {
                $io->warning('Force sync is not implemented yet. Use regular sync instead.');
                return Command::FAILURE;
            }

            $io->section('Syncing pending employees to TrackTik...');

            $syncedCount = $this->employeeService->syncAllPendingEmployees();

            $io->success([
                'Employee sync completed!',
                "Synced: $syncedCount employees"
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error([
                'Employee sync failed!',
                'Error: ' . $e->getMessage()
            ]);

            return Command::FAILURE;
        }
    }
}