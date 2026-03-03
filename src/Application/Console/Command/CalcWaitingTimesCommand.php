<?php

namespace Autarco\JiraKpi\Application\Console\Command;

use Autarco\JiraKpi\Domain\Model\Issue\IssueStatus;
use Autarco\JiraKpi\Domain\Model\Issue\WorkCategory;
use Autarco\JiraKpi\Domain\Model\Result\MonthlyWaitingTime;
use Autarco\JiraKpi\Domain\Service\KpiCalculator\DevEfficiencyCalculator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function Autarco\JiraKpi\Domain\div;

#[AsCommand(name: 'app:wait')]
class CalcWaitingTimesCommand extends AbstractKpiCommand
{
    public function __construct(
        private readonly DevEfficiencyCalculator $efficiencyCalculator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('category', InputArgument::OPTIONAL, 'Work category');
        $this->addOption('relative', 'r', InputOption::VALUE_NONE, 'Show days per story point');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($category = $input->getArgument('category')) {
            $category = WorkCategory::{strtoupper($category)};
        }

        $waitingTime = $this->efficiencyCalculator->calculateWaitingTimes($category);

        $this->renderTable($output, $input->getOption('relative'), $waitingTime);

        return Command::SUCCESS;
    }

    private function renderTable(OutputInterface $output, bool $perStoryPoint, MonthlyWaitingTime $waitingTime): void
    {
        $table = new Table($output);

        $table->setHeaders(['Status', 'Avg time']);

        $statuses = array_filter(IssueStatus::cases(), fn(IssueStatus $status): bool => $status->isCycle());

        foreach ($statuses as $status) {
            $table->addRow([
                $status->name,
                round($waitingTime->getAvgTimeInStatus($status, $perStoryPoint)->toDay()->value, 2),
            ]);
        }

        $table->addRow(new TableSeparator());
        $table->addRow([
            'Waiting',
            round($waitingTime->getAvgWaitingTime($perStoryPoint)->toDay()->value, 2),
        ]);
        $table->addRow([
            'Active',
            round($waitingTime->getAvgActiveTime($perStoryPoint)->toDay()->value, 2),
        ]);
        $table->addRow([
            'Total cycle',
            round($waitingTime->getAvgCycleTime($perStoryPoint)->toDay()->value, 2),
        ]);

        $table->render();
    }
}
