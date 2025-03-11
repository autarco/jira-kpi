<?php

namespace Marble\JiraKpi\Domain;

use Marble\JiraKpi\Domain\Model\Unit\Unit;

/**
 * @param list<int|float|Unit> $values
 * @return float
 */
function array_avg(array $values): float
{
    if (reset($values) instanceof Unit) {
        $values = array_column($values, 'value');
    }

    return div(array_sum($values), count($values));
}

function div(float|int $numerator, float|int $denominator, float $fallback = NAN): float
{
    return $denominator <> 0 ? $numerator / $denominator : $fallback;
}
