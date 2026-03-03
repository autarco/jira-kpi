<?php

namespace Autarco\JiraKpi\Application\Console\Command;

use Carbon\CarbonImmutable;
use Autarco\JiraKpi\Domain\Model\Unit\Unit;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;

abstract class AbstractKpiCommand extends Command
{
    final protected function getNumMonthsBeforeLast(): int
    {
        return (int) $_ENV['MONTHS_BEFORE_LAST'] ?? 6;
    }

    final protected function getNumWholeMonths(): int
    {
        return $this->getNumMonthsBeforeLast() + 1;
    }

    final protected function perc(float $fraction): string
    {
        return round($fraction * 100, 1) . '%';
    }

    final protected function suffix(string|int|float|Unit $suffix, bool $perc = false): string
    {
        if ($suffix instanceof Unit) {
            $suffix = $suffix->value;
        }

        if (is_float($suffix)) {
            if ($perc) {
                $suffix *= 100;
            }

            $suffix = round($suffix, 1);
        }

        if ($perc) {
            $suffix .= '%';
        }

        return sprintf(' <fg=gray>(%s)</>', $suffix);
    }

    protected function ongoing(CarbonImmutable $month): string
    {
        $result = $month->monthName;

        if (CarbonImmutable::now()->isSameMonth($month)) {
            $result .= $this->suffix('ongoing');
        }

        return $result;
    }

    protected function addHistoricalAveragesRow(Table $table, array $averages, int $spanLastColumns = 0): void
    {
        $row = ['Historical average', ...$averages];

        if ($spanLastColumns > 0) {
            $row[] = new TableCell('', ['colspan' => $spanLastColumns]);
        }

        $table->addRow(new TableSeparator());
        $table->addRow($row);
        $table->addRow(new TableSeparator());
    }
}
