<?php

namespace Marble\JiraKpi\Application\Console\Command;

use Marble\JiraKpi\Domain\Model\Result\MonthlyBugCreation;
use Marble\JiraKpi\Domain\Service\KpiCalculator\BugsAnalyzer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Marble\JiraKpi\Domain\array_avg;
use function Marble\JiraKpi\Domain\div;

#[AsCommand(name: 'app:bug-reports')]
class CalcBugsReportedCommand extends AbstractKpiCommand
{
    public function __construct(
        private readonly BugsAnalyzer $bugsAnalyzer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $analyses = $this->bugsAnalyzer->calculateCreation($this->getNumWholeMonths());

        $this->renderTable($output, ...$analyses);

        return Command::SUCCESS;
    }

    private function renderTable(OutputInterface $output, MonthlyBugCreation ...$analyses): void
    {
        $table   = new Table($output);
        $pastAvg = $this->calcHistoricalAverages(...array_slice($analyses, 0, -2));

        $table->setHeaders(['Month', 'Bugs created', 'With known cause', 'Within 1 month', 'Within 6 months', 'After 6 months', 'Avg latency', 'Bugs estimated', 'Avg estimate']);

        foreach ($analyses as $index => $analysis) {
            $within4Weeks  = $analysis->countReportedWithin(4);
            $within26Weeks = $analysis->countReportedWithin(26);

            $table->addRow([
                $this->ongoing($analysis->month),
                $analysis->created,
                count($analysis->latencies) . $this->suffix(div(count($analysis->latencies), $analysis->created), true),
                $within4Weeks,
                $within26Weeks - $within4Weeks,
                count($analysis->latencies) - $within26Weeks,
                round($analysis->getAvgLatency()->toDay()->value, 1),
                $analysis->estimated . $this->suffix($analysis->getEstimatedFraction(), true),
                round($analysis->getAvgStoryPointEstimate(), 1),
            ]);

            if ($index === count($analyses) - 3) {
                $this->addHistoricalAveragesRow($table, $pastAvg);
            }
        }

        $table->render();
    }

    private function calcHistoricalAverages(MonthlyBugCreation ...$analyses): array
    {
        $latencies = [];
        /** @noinspection PhpArrayIndexImmediatelyRewrittenInspection */
        $result = [
            'created'         => 0,
            'with-cause'      => 0,
            'within-4-weeks'  => 0,
            'within-26-weeks' => 0,
            'after-26-weeks'  => 0,
            'avg-latency'     => 0,
            'estimated'       => 0,
            'avg-estimate'    => 0,
        ];

        foreach ($analyses as $analysis) {
            $within4Weeks  = $analysis->countReportedWithin(4);
            $within26Weeks = $analysis->countReportedWithin(26);
            $latencies     = array_merge($latencies, $analysis->latencies);

            $result['created']         += $analysis->created;
            $result['with-cause']      += count($analysis->latencies);
            $result['within-4-weeks']  += $within4Weeks;
            $result['within-26-weeks'] += $within26Weeks - $within4Weeks;
            $result['after-26-weeks']  += count($analysis->latencies) - $within26Weeks;
            $result['estimated']       += $analysis->estimated;
            $result['avg-estimate']    += $analysis->getAvgStoryPointEstimate();
        }

        $result['avg-estimate']    = round(div($result['avg-estimate'], count($analyses)), 1);
        $result['estimated']       = round(div($result['estimated'], count($analyses)), 1) . $this->suffix(div($result['estimated'], $result['created']), true);
        $result['avg-latency']     = round(array_avg($latencies), 1);
        $result['after-26-weeks']  = round(div($result['after-26-weeks'], count($analyses)), 1);
        $result['within-26-weeks'] = round(div($result['within-26-weeks'], count($analyses)), 1);
        $result['within-4-weeks']  = round(div($result['within-4-weeks'], count($analyses)), 1);
        $result['with-cause']      = round(div($result['with-cause'], count($analyses)), 1);
        $result['created']         = round(div($result['created'], count($analyses)), 1);

        return $result;
    }
}
