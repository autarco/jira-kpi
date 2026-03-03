<?php

namespace Autarco\JiraKpi\Application\Console\Command;

use Autarco\JiraKpi\Domain\Model\Result\MonthlyDevIterations;
use Autarco\JiraKpi\Domain\Service\KpiCalculator\DevEfficiencyCalculator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Autarco\JiraKpi\Domain\div;

#[AsCommand(name: 'app:iterations')]
class CalcDevelopmentIterationsCommand extends AbstractKpiCommand
{
    public function __construct(
        private readonly DevEfficiencyCalculator $efficiencyCalculator,
    ) {
        // TODO: differentiate between project and non-project tickets
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $devIterations = $this->efficiencyCalculator->calculateDevIterationsUsingFeedbackToProcess($this->getNumWholeMonths());

        $this->renderTable($output, ...$devIterations);

        return Command::SUCCESS;
    }

    private function renderTable(OutputInterface $output, MonthlyDevIterations ...$devIterations): void
    {
        $table   = new Table($output);
        $pastAvg = $this->calcHistoricalAverages(...array_slice($devIterations, 0, -2));

        $table->setHeaders(['Month', 'Tickets developed', 'Avg dev iterations', 'First time right', '1st/2nd time right',
            'Most iterated ticket', '2nd most iterated', '3rd most iterated']);

        foreach ($devIterations as $index => $devIteration) {
            $mostIterated = array_map(fn(string $key, int $iterations): string => sprintf('%s%s', $key, $this->suffix($iterations)),
                array_keys($devIteration->mostIterated), $devIteration->mostIterated);

            $table->addRow([
                $this->ongoing($devIteration->month),
                $devIteration->developed,
                round($devIteration->getAvgIterations(), 1),
                $this->perc($devIteration->getFractionFirstTimeRight()) . $this->suffix($devIteration->firstTimeRight),
                $this->perc($devIteration->getFractionFirstOrSecondTimeRight()) . $this->suffix($devIteration->getFirstOrSecondTimeRight()),
                ...$mostIterated,
            ]);

            if ($index === count($devIterations) - 3) {
                $this->addHistoricalAveragesRow($table, $pastAvg, 3);
            }
        }

        $table->render();
    }

    private function calcHistoricalAverages(MonthlyDevIterations ...$devIterations): array
    {
        $result = [
            'developed'      => 0,
            'avg-iterations' => 0,
            'first-time'     => 0,
            '1st-2nd'        => 0,
        ];

        foreach ($devIterations as $devIteration) {
            $result['developed']      += $devIteration->developed;
            $result['avg-iterations'] += $devIteration->developed * $devIteration->getAvgIterations();
            $result['first-time']     += $devIteration->firstTimeRight;
            $result['1st-2nd']        += $devIteration->getFirstOrSecondTimeRight();
        }

        $result['avg-iterations'] = round(div($result['avg-iterations'], $result['developed']), 1);
        $result['first-time']     = $this->perc(div($result['first-time'], $result['developed'])) . $this->suffix(div($result['first-time'], count($devIterations)));
        $result['1st-2nd']        = $this->perc(div($result['1st-2nd'], $result['developed'])) . $this->suffix(div($result['1st-2nd'], count($devIterations)));
        $result['developed']      = round(div($result['developed'], count($devIterations)), 1);

        return $result;
    }
}
