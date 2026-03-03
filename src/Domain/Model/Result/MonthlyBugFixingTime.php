<?php

namespace Autarco\JiraKpi\Domain\Model\Result;

use Carbon\CarbonImmutable;
use Autarco\JiraKpi\Domain\Model\Unit\Second;
use function Autarco\JiraKpi\Domain\div;

readonly class MonthlyBugFixingTime
{
    public function __construct(
        public CarbonImmutable $month,
        public Second          $workingOnAny,
        public Second          $fixingBugs,
        public array           $slowest,
    ) {
    }

    public function getFractionFixingBugs(): float
    {
        return div($this->fixingBugs->value, $this->workingOnAny->value);
    }
}
