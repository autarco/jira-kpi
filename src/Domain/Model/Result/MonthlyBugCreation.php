<?php

namespace Marble\JiraKpi\Domain\Model\Result;

use Carbon\CarbonImmutable;
use Marble\JiraKpi\Domain\Model\Unit\Second;
use Marble\JiraKpi\Domain\Model\Unit\StoryPoint;
use function Marble\JiraKpi\Domain\array_avg;
use function Marble\JiraKpi\Domain\div;

readonly class MonthlyBugCreation
{
    /**
     * @param array<string, Second> $latencies // [ key => weekday seconds between causing-issue done and bug reported ]
     */
    public function __construct(
        public CarbonImmutable $month,
        public int             $created,
        public int             $estimated,
        public StoryPoint      $storyPoints,
        public array           $latencies,
    ) {
    }

    public function getEstimatedFraction(): float
    {
        return div($this->estimated, $this->created);
    }

    public function getAvgStoryPointEstimate(): float
    {
        return div($this->storyPoints->value, $this->estimated);
    }

    public function getAvgLatency(): Second
    {
        return new Second(array_avg($this->latencies));
    }

    public function countReportedWithin(int $weeks): float
    {
        $filtered = array_filter($this->latencies, fn(Second $latency) => $latency->value < $weeks * Second::WEEKDAY_SECONDS_IN_WEEK);

        return count($filtered);
    }

    public function getHottest(int $num): array
    {
        return array_slice(Second::asc($this->latencies), 0, $num, true);
    }
}
