<?php

namespace Autarco\JiraKpi\Application\Console\Command;

use Autarco\JiraKpi\Domain\Model\Issue\Timeslot;
use Autarco\JiraKpi\Domain\Model\Result\MonthlyTimePendingRelease;
use Autarco\JiraKpi\Domain\Service\KpiCalculator\DevEfficiencyCalculator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Autarco\JiraKpi\Domain\div;

#[AsCommand(name: 'app:pending-release')]
class CalcTimePendingReleaseCommand extends AbstractKpiCommand
{
    public function __construct(
        private readonly DevEfficiencyCalculator $devEfficiencyCalculator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $analyses = $this->devEfficiencyCalculator->calculateTimePendingRelease($this->getNumWholeMonths());

        $this->renderTable($output, ...$analyses);

        return Command::SUCCESS;
    }

    private function renderTable(OutputInterface $output, MonthlyTimePendingRelease ...$analyses): void
    {
        $table    = new Table($output);
        $pastAvg  = $this->calcHistoricalAverages(...array_slice($analyses, 0, -2));
        $toString = fn(Timeslot $timeslot): string => sprintf('%s%s',
            $timeslot->issue->getKey(), $this->suffix($timeslot->getDuration()->toDay()));

        $table->setHeaders(['Month', 'Releases', 'Avg time pending release', 'Slowest ticket', '2nd slowest', '3rd slowest']);

        foreach ($analyses as $index => $analysis) {
            $table->addRow([
                $this->ongoing($analysis->month),
                $analysis->released,
                round($analysis->getAvgTimePendingRelease()->toDay()->value, 1),
                ...array_map($toString, array_slice($analysis->slowest, 0, 3)),
            ]);

            if ($index === count($analyses) - 3) {
                $this->addHistoricalAveragesRow($table, $pastAvg, 3);
            }
        }

        $table->render();
    }

    private function calcHistoricalAverages(MonthlyTimePendingRelease ...$analyses): array
    {
        $result = array_fill_keys(['released', 'avg-time'], 0);

        foreach ($analyses as $analysis) {
            $result['released'] += $analysis->released;
            $result['avg-time'] += $analysis->pendingRelease->toDay()->value;
        }

        $result['avg-time'] = round(div($result['avg-time'], $result['released']), 1);
        $result['released'] = round(div($result['released'], count($analyses)), 1);

        return $result;
    }
}
