<?php

namespace Autarco\JiraKpi\Domain\Service\KpiCalculator;

use Carbon\CarbonImmutable;
use Marble\EntityManager\EntityManager;
use Autarco\JiraKpi\Domain\Model\Issue\Issue;
use Autarco\JiraKpi\Domain\Model\Issue\IssueStatus;
use Autarco\JiraKpi\Domain\Model\Issue\IssueType;
use Autarco\JiraKpi\Domain\Model\Issue\Timeslot;
use Autarco\JiraKpi\Domain\Model\Issue\WorkCategory;
use Autarco\JiraKpi\Domain\Model\Result\MonthlyTimeSpent;
use Autarco\JiraKpi\Domain\Model\Unit\Second;
use Autarco\JiraKpi\Domain\Repository\Query\TransitionedToStatusBetweenQuery;
use Autarco\JiraKpi\Domain\Service\TimeslotCalculator;
use function Symfony\Component\String\u;

class TimeAnalyzer extends AbstractKpiCalculator
{
    public function __construct(
        private readonly EntityManager      $entityManager,
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
            $end       = $month->addMonth();
            $timeslots = $this->timeslotCalculator->getTimeslotsOverlappingWith($month, $end);
            /** @var list<Timeslot> $activeTs */
            $activeTs   = array_filter($timeslots, fn(Timeslot $timeslot): bool => $timeslot->status->isActive() && $timeslot->issue->getType()->hasUsefulActiveTime());
            $categories = array_column(WorkCategory::cases(), 'name');
            /** @var array<string, Second> $seconds */
            $seconds = array_fill_keys($categories, new Second(0));

            foreach ($activeTs as $timeslot) {
                $category           = $timeslot->issue->getCategory()->name;
                $seconds[$category] = new Second($seconds[$category]->value + $timeslot->getDurationBetween($month, $end)->value);
            }

            return new MonthlyTimeSpent($month, $seconds);
        });
    }

    /**
     * @param CarbonImmutable $month
     * @return array<string, Second> [ issue key => time spent ]
     */
    public function getActiveTimeslotsInMonth(CarbonImmutable $month): array
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

    /**
     * @param CarbonImmutable $month
     * @return array<string, Second> [ issue key => time spent ]
     */
    public function getActiveTimeslotsOfDoneTickets(CarbonImmutable $month): array
    {
        $query = new TransitionedToStatusBetweenQuery(IssueStatus::DONE, $month, $month->addMonth());
        /** @var list<Issue> $issues */
        $issues = $this->entityManager->getRepository(Issue::class)->fetchMany($query);
        $issues = array_filter($issues, fn(Issue $issue): bool => $issue->getType() !== IssueType::EPIC);
        /** @var array<string, Second> $result */
        $result = [];

        foreach ($issues as $issue) {
            $timeslots = $this->timeslotCalculator->calculateTimeslots($issue);
            $seconds   = array_map(fn(Timeslot $timeslot): float => $timeslot->status->isActive() ? $timeslot->getDuration()->value : 0, $timeslots);

            $result[$issue->getKey()] = new Second(array_sum($seconds));
        }

        return Second::desc($result);
    }
}
