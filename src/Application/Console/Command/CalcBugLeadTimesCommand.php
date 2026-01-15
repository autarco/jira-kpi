<?php

namespace Marble\JiraKpi\Application\Console\Command;

use Marble\JiraKpi\Domain\Model\Result\MonthlyBugLeadTime;
use Marble\JiraKpi\Domain\Model\Unit\Second;
use Marble\JiraKpi\Domain\Service\KpiCalculator\BugsAnalyzer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Marble\JiraKpi\Domain\div;

#[AsCommand(name: 'app:bug-lead')]
class CalcBugLeadTimesCommand extends AbstractKpiCommand
{
    public function __construct(
        private readonly BugsAnalyzer $bugsAnalyzer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $analyses = $this->bugsAnalyzer->calculateLeadTime($this->getNumWholeMonths());

        $this->renderLeadTimesTable($output, ...$analyses);
        $this->renderLatenciesTable($output, ...$analyses);

        return Command::SUCCESS;
    }

    private function renderLeadTimesTable(OutputInterface $output, MonthlyBugLeadTime ...$analyses): void
    {
        $table   = new Table($output);
        $pastAvg = $this->calcHistoricalLeadTimeAverages(...array_slice($analyses, 0, -2));

        $table->setHeaders(['Month', 'Bugs fixed', 'Avg bug lead time', 'Max 6 months old', 'Max 2 months old', 'Slowest bug', '2nd slowest', '3rd slowest']);

        foreach ($analyses as $index => $analysis) {
            $slowest = $analysis->getSlowest(3);
            $slowest = array_map(fn(string $key, Second $leadTime): string => sprintf('%s%s', $key, $this->suffix($leadTime->toDay())),
                array_keys($slowest), $slowest);

            $table->addRow([
                $this->ongoing($analysis->month),
                $analysis->fixed,
                round($analysis->getAvgLeadTime()->toDay()->value, 1),
                round($analysis->getAvgLeadTimeMaxAge(26)->toDay()->value, 1),
                round($analysis->getAvgLeadTimeMaxAge(8)->toDay()->value, 1),
                ...$slowest,
            ]);

            if ($index === count($analyses) - 3) {
                $this->addHistoricalAveragesRow($table, $pastAvg, 3);
            }
        }

        $table->render();
    }

    private function calcHistoricalLeadTimeAverages(MonthlyBugLeadTime ...$analyses): array
    {
        $result = [
            'fixed'         => 0,
            'avg-lead-time' => 0,
            'max-6-months'  => 0,
            'max-2-months'  => 0,
        ];

        foreach ($analyses as $analysis) {
            $result['fixed']         += $analysis->fixed;
            $result['avg-lead-time'] += $analysis->fixed * $analysis->getAvgLeadTime()->toDay()->value;
            $result['max-6-months']  += $analysis->fixed * $analysis->getAvgLeadTimeMaxAge(26)->toDay()->value;
            $result['max-2-months']  += $analysis->fixed * $analysis->getAvgLeadTimeMaxAge(8)->toDay()->value;
        }

        $result['avg-lead-time'] = round(div($result['avg-lead-time'], $result['fixed']), 1);
        $result['max-6-months']  = round(div($result['max-6-months'], $result['fixed']), 1);
        $result['max-2-months']  = round(div($result['max-2-months'], $result['fixed']), 1);
        $result['fixed']         = round(div($result['fixed'], count($analyses)), 1);

        return $result;
    }

    private function calcHistoricalLatencyAverages(MonthlyBugLeadTime ...$analyses): array
    {
        $result = [
            'fixed'           => 0,
            'avg-latency'     => 0,
            'within-1-week'   => 0,
            'within-2-weeks'  => 0,
            'within-2-months' => 0,
            'within-6-months' => 0,
        ];

        foreach ($analyses as $analysis) {
            $result['fixed']           += $analysis->fixed;
            $result['avg-latency']     += $analysis->fixed * $analysis->getAvgLatency()->toDay()->value;
            $result['within-1-week']   += $analysis->fixed * $analysis->getFractionReportedWithin(1);
            $result['within-2-weeks']  += $analysis->fixed * $analysis->getFractionReportedWithin(2);
            $result['within-2-months'] += $analysis->fixed * $analysis->getFractionReportedWithin(8);
            $result['within-6-months'] += $analysis->fixed * $analysis->getFractionReportedWithin(26);
        }

        $result['avg-latency']     = round(div($result['avg-latency'], $result['fixed']), 1);
        $result['within-1-week']   = round(div($result['within-1-week'], $result['fixed']), 1);
        $result['within-2-weeks']  = round(div($result['within-2-weeks'], $result['fixed']), 1);
        $result['within-2-months'] = round(div($result['within-2-months'], $result['fixed']), 1);
        $result['within-6-months'] = round(div($result['within-6-months'], $result['fixed']), 1);
        $result['fixed']           = round(div($result['fixed'], count($analyses)), 1);

        return $result;
    }
}
