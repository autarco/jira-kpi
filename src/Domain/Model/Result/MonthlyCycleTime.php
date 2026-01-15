<?php

namespace Marble\JiraKpi\Domain\Model\Result;

use Carbon\CarbonImmutable;
use Marble\JiraKpi\Domain\Model\Unit\Second;
use function Marble\JiraKpi\Domain\array_avg;

readonly class MonthlyCycleTime
{
    /** @var array<string, Second> */
    public array $cycleTimes;

    /**
     * @param CarbonImmutable       $month
     * @param int                   $done
     * @param array<string, Second> $cycleTimes
     */
    public function __construct(
        public CarbonImmutable $month,
        public int             $done,
        array                  $cycleTimes,
    ) {
        $this->cycleTimes = Second::desc($cycleTimes);
    }

    public function getAvgCycleTime(bool $ignoreSlowest = false): Second
    {
        $cycleTimes = $this->cycleTimes;

        if ($ignoreSlowest && count($cycleTimes) > 1) {
            array_shift($cycleTimes); // drop slowest one
        }

        return new Second(array_avg($cycleTimes));
    }

    public function getSlowest(int $num = 3): array
    {
        return array_slice($this->cycleTimes, 0, $num, true);
    }

    public function getQuantile(int $which, int $parts = 4): ?Second
    {
        $cycleTimes = array_values(array_reverse($this->cycleTimes, true)); // asc
        $size       = count($cycleTimes);
        $index      = (int) floor($which * ($size / $parts));

        return $size > 0 ? $cycleTimes[$index] : null;
    }
}
