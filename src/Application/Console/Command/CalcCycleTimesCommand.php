<?php

namespace Marble\JiraKpi\Application\Console\Command;

use Marble\JiraKpi\Domain\Model\Result\MonthlyCycleTime;
use Marble\JiraKpi\Domain\Model\Unit\Second;
use Marble\JiraKpi\Domain\Service\KpiCalculator\DevEfficiencyCalculator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Marble\JiraKpi\Domain\div;

#[AsCommand(name: 'app:cycle')]
class CalcCycleTimesCommand extends AbstractKpiCommand
{
    public function __construct(
        private readonly DevEfficiencyCalculator $efficiencyCalculator,
    ) {
        // TODO: differentiate between project and non-project tickets
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cycleTimes = $this->efficiencyCalculator->calculateCycleTime($this->getNumWholeMonths());

        $this->renderTable($output, ...$cycleTimes);

        return Command::SUCCESS;
    }

    private function renderTable(OutputInterface $output, MonthlyCycleTime ...$cycleTimes): void
    {
        $table   = new Table($output);
        $pastAvg = $this->calcHistoricalAverages(...array_slice($cycleTimes, 0, -2));

        $table->setHeaders(['Month', 'Tickets done', 'Avg cycle time', 'Without slowest', 'Slowest ticket', '2nd slowest', '3rd slowest', 'Q3', 'Q2', 'Q1']);

        foreach ($cycleTimes as $index => $cycleTime) {
            $slowest = $cycleTime->getSlowest();
            $slowest = array_map(fn(string $key, Second $leadTime): string => sprintf('%s%s', $key, $this->suffix($leadTime->toDay())),
                array_keys($slowest), $slowest);

            $table->addRow([
                $this->ongoing($cycleTime->month),
                $cycleTime->done,
                round($cycleTime->getAvgCycleTime()->toDay()->value, 1),
                round($cycleTime->getAvgCycleTime(true)->toDay()->value, 1),
                ...array_slice($slowest, 0, 3),
                round($cycleTime->getQuantile(3)->toDay()->value, 1),
                round($cycleTime->getQuantile(2)->toDay()->value, 1),
                round($cycleTime->getQuantile(1)->toDay()->value, 1),
            ]);

            if ($index === count($cycleTimes) - 3) {
                $this->addHistoricalAveragesRow($table, $pastAvg, 6);
            }
        }

        $table->render();
    }

    private function calcHistoricalAverages(MonthlyCycleTime ...$cycleTimes): array
    {
        $result = [
            'done'            => 0,
            'avg-cycle-time'  => 0,
            'without-slowest' => 0,
        ];

        foreach ($cycleTimes as $cycleTime) {
            $result['done']            += $cycleTime->done;
            $result['avg-cycle-time']  += $cycleTime->done * $cycleTime->getAvgCycleTime()->toDay()->value;
            $result['without-slowest'] += ($cycleTime->done - 1) * $cycleTime->getAvgCycleTime(true)->toDay()->value;
        }

        $result['avg-cycle-time']  = round(div($result['avg-cycle-time'], $result['done']), 1);
        $result['without-slowest'] = round(div($result['without-slowest'], ($result['done'] - count($cycleTimes))), 1);
        $result['done']            = round(div($result['done'], count($cycleTimes)), 1);

        return $result;
    }
}
