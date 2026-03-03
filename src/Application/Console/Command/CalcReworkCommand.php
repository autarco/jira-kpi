<?php

namespace Autarco\JiraKpi\Application\Console\Command;

use Autarco\JiraKpi\Domain\Model\Result\MonthlyRework;
use Autarco\JiraKpi\Domain\Model\Unit\Day;
use Autarco\JiraKpi\Domain\Model\Unit\Second;
use Autarco\JiraKpi\Domain\Model\Unit\StoryPoint;
use Autarco\JiraKpi\Domain\Service\KpiCalculator\ReworkCalculator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Autarco\JiraKpi\Domain\array_avg;

#[AsCommand(name: 'app:rework')]
class CalcReworkCommand extends AbstractKpiCommand
{
    private const int TOO_SOON = 8; // weeks

    public function __construct(
        private readonly ReworkCalculator $calculator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tooSoon = new Day(5 * self::TOO_SOON);
        $reworks = $this->calculator->calculate($this->getNumWholeMonths(), $tooSoon);

        $this->renderMetricsTable($output, ...$reworks);
        $this->renderMonthTable($output, $reworks[count($reworks) - 2]);

        return Command::SUCCESS;
    }

    private function renderMetricsTable(OutputInterface $output, MonthlyRework ...$reworks): void
    {
        $table   = new Table($output);
        $pastAvg = $this->calcHistoricalAverages(...array_slice($reworks, 0, -2));

        $table->setHeaders(['Month', 'Bugs', 'Gaps', 'Total']);

        foreach ($reworks as $index => $rework) {
            $table->addRow([
                $this->ongoing($rework->month),
                $rework->getUrgentBugsFixed()->value . $this->suffix($rework->getFractionUrgentBugs(), true),
                $rework->getUrgentGapsFixed()->value . $this->suffix($rework->getFractionUrgentGaps(), true),
                $rework->getTotalUrgentRework()->value . $this->suffix($rework->getFractionUrgentRework(), true),
            ]);

            if ($index === count($reworks) - 3) {
                $this->addHistoricalAveragesRow($table, $pastAvg);
            }
        }

        $table->render();
    }

    private function renderMonthTable(OutputInterface $output, MonthlyRework $rework): void
    {
        $table = new Table($output);

        $table->setHeaders(['Issue', 'Type', 'Story points', 'Cause', 'Latency']);

        foreach ($rework->issues as $issue) {
            if ($cause = $rework->getCause($issue)) {
                $table->addRow([
                    $issue->getKey() . ' ' . $issue->getSummary(),
                    $issue->getType()->name,
                    $issue->getEstimate()->value,
                    $cause->getIssue()->getKey() . ' ' . $cause->getIssue()->getSummary(),
                    round((new Second($cause->getTransitioned()->diffInWeekdaySeconds($issue->getCreated())))->toDay()->value, 1),
                ]);
            }
        }

        $table->render();
    }

    private function calcHistoricalAverages(MonthlyRework ...$reworks): array
    {
        return [
            'avgBugsFixed' => round(array_avg(array_map(fn(MonthlyRework $rework): StoryPoint => $rework->getUrgentBugsFixed(), $reworks)), 1),
            'avgGapsFixed' => round(array_avg(array_map(fn(MonthlyRework $rework): StoryPoint => $rework->getUrgentGapsFixed(), $reworks)), 1),
            'avgTotal'     => round(array_avg(array_map(fn(MonthlyRework $rework): StoryPoint => $rework->getTotalUrgentRework(), $reworks)), 1),
        ];
    }
}
