<?php

namespace Marble\JiraKpi\Domain\Model\Result;

use Carbon\CarbonImmutable;
use Marble\JiraKpi\Domain\Model\Issue\IssueType;
use Marble\JiraKpi\Domain\Model\Unit\StoryPoint;
use function Marble\JiraKpi\Domain\div;

readonly class MonthlyVelocity
{
    /**
     * @param CarbonImmutable $month
     * @param array<string, StoryPoint> $storyPointsPerIssueType
     */
    public function __construct(
        public CarbonImmutable $month,
        public array           $storyPointsPerIssueType,
    ) {
    }

    public function getTotal(): int
    {
        return array_sum(array_column($this->storyPointsPerIssueType, 'value'));
    }

    public function getFractionByType(IssueType $type): float
    {
        $ofType = $this->storyPointsPerIssueType[$type->name]->value ?? 0;
        $total  = $this->getTotal();

        return div($ofType, $total, 0);
    }
}
