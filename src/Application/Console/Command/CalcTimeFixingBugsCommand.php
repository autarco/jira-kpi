<?php

namespace Autarco\JiraKpi\Application\Console\Command;

use Autarco\JiraKpi\Domain\Model\Issue\Timeslot;
use Autarco\JiraKpi\Domain\Model\Result\MonthlyBugFixingTime;
use Autarco\JiraKpi\Domain\Service\KpiCalculator\BugsAnalyzer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Autarco\JiraKpi\Domain\div;
use function Symfony\Component\String\u;

#[AsCommand(name: 'app:bug-ratio')]
class CalcTimeFixingBugsCommand extends AbstractKpiCommand
{
    public function __construct(
        private readonly BugsAnalyzer $bugsAnalyzer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $analyses = $this->bugsAnalyzer->calculateTimeFixingBugs($this->getNumWholeMonths());

        $this->renderTable($output, ...$analyses);

        return Command::SUCCESS;
    }

    private function renderTable(OutputInterface $output, MonthlyBugFixingTime ...$analyses): void
    {
        $table    = new Table($output);
        $pastAvg  = $this->calcHistoricalAverages(...array_slice($analyses, 0, -2));
        $toString = fn(Timeslot $timeslot): string => sprintf('%s %s%s',
            $timeslot->issue->getKey(), u($timeslot->status->name)->lower()->replace('_', ' '), $this->suffix($timeslot->getDuration()->toDay()));

        $table->setHeaders(['Month', 'Cumulative active time', 'Active time bugs', 'Slowest bug timeslot', '2nd slowest', '3rd slowest']);

        foreach ($analyses as $index => $analysis) {
            $table->addRow([
                $this->ongoing($analysis->month),
                round($analysis->workingOnAny->toDay()->value, 1),
                $this->perc($analysis->getFractionFixingBugs()) . $this->suffix($analysis->fixingBugs->toDay()),
                ...array_map($toString, array_slice($analysis->slowest, 0, 3)),
            ]);

            if ($index === count($analyses) - 3) {
                $this->addHistoricalAveragesRow($table, $pastAvg, 3);
            }
        }

        $table->render();
    }

    private function calcHistoricalAverages(MonthlyBugFixingTime ...$analyses): array
    {
        $result = array_fill_keys(['any', 'bugs'], 0);

        foreach ($analyses as $analysis) {
            $result['any']  += $analysis->workingOnAny->toDay()->value;
            $result['bugs'] += $analysis->fixingBugs->toDay()->value;
        }

        $result['bugs'] = $this->perc(div($result['bugs'], $result['any'])) . $this->suffix(div($result['bugs'], count($analyses)));
        $result['any']  = round(div($result['any'], count($analyses)), 1);

        return $result;
    }
}
