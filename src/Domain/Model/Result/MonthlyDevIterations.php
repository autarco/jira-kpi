<?php

namespace Autarco\JiraKpi\Domain\Model\Result;

use Carbon\CarbonImmutable;
use function Autarco\JiraKpi\Domain\div;

readonly class MonthlyDevIterations
{
    public function __construct(
        public CarbonImmutable $month,
        public int             $developed,
        public int             $iterations,
        public int             $firstTimeRight,
        public int             $secondTimeRight,
        public array           $mostIterated,
    ) {
    }

    public function getAvgIterations(): float
    {
        return div($this->iterations, $this->developed);
    }

    public function getFractionFirstTimeRight(): float
    {
        return div($this->firstTimeRight, $this->developed);
    }

    public function getFractionSecondTimeRight(): float
    {
        return div($this->secondTimeRight, $this->developed);
    }

    public function getFirstOrSecondTimeRight(): int
    {
        return $this->firstTimeRight + $this->secondTimeRight;
    }

    public function getFractionFirstOrSecondTimeRight(): float
    {
        return div($this->getFirstOrSecondTimeRight(), $this->developed);
    }
}
