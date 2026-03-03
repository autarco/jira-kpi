<?php

namespace Autarco\JiraKpi\Domain\Model\Result;

use Carbon\CarbonImmutable;
use Autarco\JiraKpi\Domain\Model\Issue\IssueType;
use Autarco\JiraKpi\Domain\Model\Issue\WorkCategory;
use Autarco\JiraKpi\Domain\Model\Unit\StoryPoint;
use function Autarco\JiraKpi\Domain\div;

readonly class MonthlyVelocity
{
    /**
     * @param CarbonImmutable           $month
     * @param array<string, StoryPoint> $storyPointsPerIssueType
     * @param array<string, StoryPoint> $storyPointsPerWorkCategory
     */
    public function __construct(
        public CarbonImmutable $month,
        public array           $storyPointsPerIssueType,
        public array           $storyPointsPerWorkCategory,
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

    public function getFractionByCategory(WorkCategory $category): float
    {
        $ofCategory = $this->storyPointsPerWorkCategory[$category->name]->value ?? 0;
        $total      = $this->getTotal();

        return div($ofCategory, $total, 0);
    }
}
