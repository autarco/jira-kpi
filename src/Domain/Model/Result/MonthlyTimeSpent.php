<?php

namespace Marble\JiraKpi\Domain\Model\Result;

use Carbon\CarbonImmutable;
use Marble\JiraKpi\Domain\Model\Issue\IssueType;
use Marble\JiraKpi\Domain\Model\Unit\Second;
use function Marble\JiraKpi\Domain\div;

readonly class MonthlyTimeSpent
{
    /**
     * @param CarbonImmutable       $month
     * @param array<string, Second> $timePerIssueType
     */
    public function __construct(
        public CarbonImmutable $month,
        public array           $timePerIssueType,
    ) {
    }

    public function getTotal(): Second
    {
        return new Second(array_sum(array_column($this->timePerIssueType, 'value')));
    }

    public function getFractionByType(IssueType $type): float
    {
        $ofType = $this->timePerIssueType[$type->name]->value ?? 0;
        $total  = $this->getTotal()->value;

        return div($ofType, $total, 0);
    }
}
