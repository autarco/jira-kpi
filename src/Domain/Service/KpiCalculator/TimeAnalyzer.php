<?php

namespace Marble\JiraKpi\Domain\Service\KpiCalculator;

use Carbon\CarbonImmutable;
use Marble\JiraKpi\Domain\Model\Issue\Issue;
use Marble\JiraKpi\Domain\Model\Issue\IssueType;
use Marble\JiraKpi\Domain\Model\Issue\Timeslot;
use Marble\JiraKpi\Domain\Model\Result\MonthlyTimeSpent;
use Marble\JiraKpi\Domain\Model\Unit\Second;
use Marble\JiraKpi\Domain\Service\TimeslotCalculator;

class TimeAnalyzer extends AbstractKpiCalculator
{
    public function __construct(
        private readonly TimeslotCalculator $timeslotCalculator,
    ) {
    }

    /**
     * @param int $monthsBeforeLast
     * @return list<MonthlyTimeSpent>
     */
    public function calculateRelativeTimeSpent(int $monthsBeforeLast): array
    {
        return $this->perMonth($monthsBeforeLast, function (CarbonImmutable $month): MonthlyTimeSpent {
            $end         = $month->addMonth();
            $timeslots   = $this->timeslotCalculator->getTimeslotsOverlappingWith($month, $end);
            $activeTs    = array_filter($timeslots, fn(Timeslot $timeslot): bool => $timeslot->status->isActive() && $timeslot->issue->getType()->hasUsefulActiveTime());
            $usefulTypes = array_filter(IssueType::cases(), fn(IssueType $type): bool => $type->hasUsefulActiveTime());
            /** @var array<string, Second> $seconds */
            $seconds = array_fill_keys(array_column($usefulTypes, 'name'), new Second(0));

            foreach ($activeTs as $timeslot) {
                $type           = $timeslot->issue->getType()->name;
                $seconds[$type] = new Second($seconds[$type]->value + $timeslot->getDurationBetween($month, $end)->value);
            }

            return new MonthlyTimeSpent($month, $seconds);
        });
    }

    /**
     * @param CarbonImmutable $month
     * @return array<string, Second> [ issue key => time spent ]
     */
    public function getActiveTimeslots(CarbonImmutable $month): array
    {
        $end       = $month->addMonth();
        $timeslots = $this->timeslotCalculator->getTimeslotsOverlappingWith($month, $end);
        /** @var array<string, Second> $result */
        $result = [];

        foreach ($timeslots as $timeslot) {
            if ($timeslot->status->isActive() && $timeslot->issue->getType()->hasUsefulActiveTime()) {
                $key          = $timeslot->issue->getKey();
                $base         = $result[$key] ?? new Second(0);
                $result[$key] = new Second($base->value + $timeslot->getDurationBetween($month, $end)->value);
            }
        }

        return Second::desc($result);
    }
}
