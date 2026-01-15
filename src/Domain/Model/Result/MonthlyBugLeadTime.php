<?php

namespace Marble\JiraKpi\Domain\Model\Result;

use Carbon\CarbonImmutable;
use Marble\JiraKpi\Domain\Model\Unit\Second;
use function Marble\JiraKpi\Domain\array_avg;

readonly class MonthlyBugLeadTime
{
    /**
     * @param array<string, Second> $leadTimes // [ key => weekday seconds between bug reported and bug fixed ]
     */
    public function __construct(
        public CarbonImmutable $month,
        public int             $fixed,
        public array           $leadTimes,
    ) {
    }

    public function getAvgLeadTime(): Second
    {
        return new Second(array_avg($this->leadTimes));
    }

    public function getAvgLeadTimeMaxAge(int $weeks): Second
    {
        $leadTimes = array_filter($this->leadTimes, fn(Second $leadTime) => $leadTime->value < $weeks * Second::WEEKDAY_SECONDS_IN_WEEK);

        return new Second(array_avg($leadTimes));
    }

    public function getSlowest(int $num): array
    {
        return array_slice(Second::desc($this->leadTimes), 0, $num, true);
    }
}
