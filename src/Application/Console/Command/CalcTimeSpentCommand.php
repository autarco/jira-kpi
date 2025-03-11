<?php

namespace Marble\JiraKpi\Application\Console\Command;

use Carbon\CarbonImmutable;
use Marble\EntityManager\EntityManager;
use Marble\JiraKpi\Domain\Model\Issue\Issue;
use Marble\JiraKpi\Domain\Model\Issue\IssueType;
use Marble\JiraKpi\Domain\Model\Result\MonthlyTimeSpent;
use Marble\JiraKpi\Domain\Service\KpiCalculator\TimeAnalyzer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Marble\JiraKpi\Domain\div;
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
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($month = $input->getArgument('month')) {
            $this->printActiveIssuesInMonth($output, $month);
        } else {
            $analyses = $this->analyzer->calculateRelativeTimeSpent($this->getNumWholeMonths());

            $this->renderTable($output, ...$analyses);
        }

        return Command::SUCCESS;
    }

    private function renderTable(OutputInterface $output, MonthlyTimeSpent ...$times): void
    {
        $table       = new Table($output);
        $usefulTypes = array_filter(IssueType::cases(), fn(IssueType $type): bool => $type->hasUsefulActiveTime());
        $typeNames   = array_column($usefulTypes, 'name');
        $typeHeaders = array_map(fn(string $name): string => u($name)->lower()->title(), $typeNames);
        $pastAvg     = $this->calcHistoricalAverages(...array_slice($times, 0, -2));

        $table->setHeaders(['Month', ...$typeHeaders, 'Total']);

        foreach ($times as $index => $time) {
            $timePerType = array_fill_keys($typeNames, 0);

            foreach ($time->timePerIssueType as $type => $second) {
                $timePerType[$type] = $this->perc($time->getFractionByType(IssueType::{$type})) . $this->suffix($second->toDay()->value);
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
        $usefulTypes = array_filter(IssueType::cases(), fn(IssueType $type): bool => $type->hasUsefulActiveTime());
        $typeNames   = array_column($usefulTypes, 'name');
        $timePerType = array_fill_keys($typeNames, 0);

        foreach ($times as $time) {
            foreach ($time->timePerIssueType as $type => $second) {
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

    private function printActiveIssuesInMonth(OutputInterface $output, string $month): void
    {
        $table = new Table($output);

        $table->setHeaders(['Issue', 'Type', 'Active time', 'SP', 'Status', 'Title']);

        $times = $this->analyzer->getActiveTimeslots(new CarbonImmutable($month));

        foreach ($times as $key => $time) {
            /** @var Issue $issue */
            $issue = $this->entityManager->getRepository(Issue::class)->fetchOneBy(['key' => $key]);

            $table->addRow([
                $key,
                $issue->getType()->name,
                round($time->toDay()->value, 1),
                $issue->getEstimate()->value,
                $issue->getStatus()->name,
                $issue->getSummary(),
            ]);
        }

        $table->render();
    }
}
