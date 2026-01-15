<?php

namespace Marble\JiraKpi\Domain\Model\Result;

use Carbon\CarbonImmutable;
use Marble\JiraKpi\Domain\Model\Issue\IssueType;
use Marble\JiraKpi\Domain\Model\Issue\WorkCategory;
use Marble\JiraKpi\Domain\Model\Unit\Second;
use function Marble\JiraKpi\Domain\div;

readonly class MonthlyTimeSpent
{
    /**
     * @param CarbonImmutable       $month
     * @param array<string, Second> $timePerCategory
     */
    public function __construct(
        public CarbonImmutable $month,
        public array           $timePerCategory,
    ) {
    }

    public function getTotal(): Second
    {
        return new Second(array_sum(array_column($this->timePerCategory, 'value')));
    }

    public function getFractionByCategory(WorkCategory $type): float
    {
        $ofType = $this->timePerCategory[$type->name]->value ?? 0;
        $total  = $this->getTotal()->value;

        return div($ofType, $total, 0);
    }
}
