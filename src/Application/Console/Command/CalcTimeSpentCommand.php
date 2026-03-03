<?php

namespace Autarco\JiraKpi\Application\Console\Command;

use Carbon\CarbonImmutable;
use Marble\EntityManager\EntityManager;
use Autarco\JiraKpi\Domain\Model\Issue\Issue;
use Autarco\JiraKpi\Domain\Model\Issue\WorkCategory;
use Autarco\JiraKpi\Domain\Model\Result\MonthlyTimeSpent;
use Autarco\JiraKpi\Domain\Service\KpiCalculator\TimeAnalyzer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function Autarco\JiraKpi\Domain\div;
use function Symfony\Component\String\u;

#[AsCommand(name: 'app:time-spent')]
class CalcTimeSpentCommand extends AbstractKpiCommand
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly TimeAnalyzer  $analyzer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('month', InputArgument::OPTIONAL, 'Month');
        $this->addOption('constrain', 'c', InputOption::VALUE_NONE, 'Only consider time during month');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($month = $input->getArgument('month')) {
            $in = $input->getOption('constrain');

            $this->printActiveIssuesInMonth($output, $month, $in);
        } else {
            $analyses = $this->analyzer->calculateRelativeTimeSpent($this->getNumWholeMonths());

            $this->renderTable($output, ...$analyses);
        }

        return Command::SUCCESS;
    }

    private function renderTable(OutputInterface $output, MonthlyTimeSpent ...$times): void
    {
        $table       = new Table($output);
        $typeNames   = array_column(WorkCategory::cases(), 'name');
        $typeHeaders = array_map(fn(string $name): string => u($name)->lower()->title(), $typeNames);
        $pastAvg     = $this->calcHistoricalAverages(...array_slice($times, 0, -2));

        $table->setHeaders(['Month', ...$typeHeaders, 'Total']);

        foreach ($times as $index => $time) {
            $timePerType = array_fill_keys($typeNames, 0);

            foreach ($time->timePerCategory as $type => $second) {
                $timePerType[$type] = $this->perc($time->getFractionByCategory(WorkCategory::{$type})) . $this->suffix($second->toDay()->value);
            }

            $table->addRow([
                $this->ongoing($time->month),
                ...$timePerType,
                round($time->getTotal()->toDay()->value, 1),
            ]);

            if ($index === count($times) - 3) {
                $this->addHistoricalAveragesRow($table, $pastAvg);
            }
        }

        $table->render();
    }

    private function calcHistoricalAverages(MonthlyTimeSpent ...$times): array
    {
        $typeNames   = array_column(WorkCategory::cases(), 'name');
        $timePerType = array_fill_keys($typeNames, 0);

        foreach ($times as $time) {
            foreach ($time->timePerCategory as $type => $second) {
                $timePerType[$type] += $second->toDay()->value;
            }
        }

        $total = array_sum($timePerType);

        foreach ($timePerType as $type => $day) {
            $timePerType[$type] = $this->perc(div($day, $total)) . $this->suffix(div($day, count($times)));
        }

        $timePerType['*'] = round(div($total, count($times)), 1);

        return $timePerType;
    }

    private function printActiveIssuesInMonth(OutputInterface $output, string $month, bool $onlyTimeInMonth): void
    {
        $table = new Table($output);

        $table->setHeaders(['Issue', 'Type', 'Active time', 'SP', 'Status', 'Title']);

        if ($onlyTimeInMonth) {
            $times = $this->analyzer->getActiveTimeslotsInMonth(new CarbonImmutable($month));
        } else {
            $times = $this->analyzer->getActiveTimeslotsOfDoneTickets(new CarbonImmutable($month));
        }

        foreach ($times as $key => $time) {
            /** @var Issue $issue */
            $issue = $this->entityManager->getRepository(Issue::class)->fetchOneBy(['key' => $key]);

            $table->addRow([
                $key,
                $issue->getType()->name,
                round($time->toDay()->value, 1),
                $issue->getEstimate()?->value,
                $issue->getStatus()->name,
                $issue->getSummary(),
            ]);
        }

        $table->render();
    }
}
