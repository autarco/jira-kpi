<?php

namespace Autarco\JiraKpi\Domain\Service\KpiCalculator;

use Carbon\CarbonImmutable;

abstract class AbstractKpiCalculator
{
    protected function perMonth(int $numWholeMonths, callable $fn): array
    {
        $start   = CarbonImmutable::now()->startOfMonth()->subMonths($numWholeMonths);
        $results = [];

        while (!$start->isFuture()) {
            $results[] = $fn($start);
            $start     = $start->addMonth();
        }

        return $results;
    }
}
