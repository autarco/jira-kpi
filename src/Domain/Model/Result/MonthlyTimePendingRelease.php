<?php

namespace Autarco\JiraKpi\Domain\Model\Result;

use Carbon\CarbonImmutable;
use Autarco\JiraKpi\Domain\Model\Unit\Second;
use function Autarco\JiraKpi\Domain\div;

readonly class MonthlyTimePendingRelease
{
    public function __construct(
        public CarbonImmutable $month,
        public int             $released,
        public Second          $pendingRelease,
        public array           $slowest,
    ) {
    }

    public function getAvgTimePendingRelease(): Second
    {
        return new Second(div($this->pendingRelease->value, $this->released));
    }
}
