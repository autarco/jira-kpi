<?php

namespace Autarco\JiraKpi\Application\Console\Command;

use Autarco\JiraKpi\Domain\Model\Issue\IssueType;
use Autarco\JiraKpi\Domain\Model\Issue\WorkCategory;
use Autarco\JiraKpi\Domain\Model\Result\MonthlyVelocity;
use Autarco\JiraKpi\Domain\Service\KpiCalculator\VelocityCalculator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Autarco\JiraKpi\Domain\div;
use function Symfony\Component\String\u;

#[AsCommand(name: 'app:velocity')]
class CalcVelocityCommand extends AbstractKpiCommand
{
    public function __construct(
        private readonly VelocityCalculator $calculator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $velocities = $this->calculator->calculate($this->getNumWholeMonths());

        $this->renderTypesTable($output, ...$velocities);
        $this->renderCategoriesTable($output, ...$velocities);

        return Command::SUCCESS;
    }

    private function renderTypesTable(OutputInterface $output, MonthlyVelocity ...$velocities): void
    {
        $table       = new Table($output);
        $typeNames   = array_column(IssueType::withStoryPoints(), 'name');
        $typeHeaders = array_map(fn(string $name): string => u($name)->lower()->title(), $typeNames);
        $pastAvg     = $this->calcHistoricalAverages(IssueType::withStoryPoints(), 'storyPointsPerIssueType', ...array_slice($velocities, 0, -2));

        $table->setHeaders(['Month', ...$typeHeaders, 'Total']);

        foreach ($velocities as $index => $velocity) {
            $storyPointsPerType = array_fill_keys($typeNames, 0);

            foreach ($velocity->storyPointsPerIssueType as $type => $storyPoints) {
                $storyPointsPerType[$type] = $storyPoints->value . $this->suffix($velocity->getFractionByType(IssueType::{$type}), true);
            }

            $table->addRow([
                $this->ongoing($velocity->month),
                ...$storyPointsPerType,
                $velocity->getTotal(),
            ]);

            if ($index === count($velocities) - 3) {
                $this->addHistoricalAveragesRow($table, $pastAvg);
            }
        }

        $table->render();
    }

    private function renderCategoriesTable(OutputInterface $output, MonthlyVelocity ...$velocities): void
    {
        $table   = new Table($output);
        $names   = array_column(WorkCategory::cases(), 'name');
        $headers = array_map(fn(string $name): string => u($name)->lower()->title(), $names);
        $pastAvg = $this->calcHistoricalAverages(WorkCategory::cases(), 'storyPointsPerWorkCategory', ...array_slice($velocities, 0, -2));

        $table->setHeaders(['Month', ...$headers, 'Total']);

        foreach ($velocities as $index => $velocity) {
            $storyPointsPerCategory = array_fill_keys($names, 0);

            foreach ($velocity->storyPointsPerWorkCategory as $category => $storyPoints) {
                $storyPointsPerCategory[$category] = $storyPoints->value . $this->suffix($velocity->getFractionByCategory(WorkCategory::{$category}), true);
            }

            $table->addRow([
                $this->ongoing($velocity->month),
                ...$storyPointsPerCategory,
                $velocity->getTotal(),
            ]);

            if ($index === count($velocities) - 3) {
                $this->addHistoricalAveragesRow($table, $pastAvg);
            }
        }

        $table->render();
    }

    private function calcHistoricalAverages(array $cases, string $prop, MonthlyVelocity ...$velocities): array
    {
        $typeNames          = array_column($cases, 'name');
        $storyPointsPerType = array_fill_keys($typeNames, 0);

        foreach ($velocities as $velocity) {
            foreach ($velocity->$prop as $type => $storyPoints) {
                $storyPointsPerType[$type] += $storyPoints->value;
            }
        }

        $total = array_sum($storyPointsPerType);

        foreach ($storyPointsPerType as $type => $storyPoints) {
            $storyPointsPerType[$type] = round(div($storyPoints, count($velocities)), 1) . $this->suffix(div($storyPoints, $total), true);
        }

        $storyPointsPerType['*'] = round(div($total, count($velocities)), 1);

        return $storyPointsPerType;
    }
}
