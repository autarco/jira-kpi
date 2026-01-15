<?php

namespace Marble\JiraKpi\Domain\Service\KpiCalculator;

use Carbon\CarbonImmutable;
use Hamcrest\Core\Is;
use Marble\Entity\SimpleId;
use Marble\EntityManager\EntityManager;
use Marble\JiraKpi\Domain\Model\Issue\Issue;
use Marble\JiraKpi\Domain\Model\Issue\IssueStatus;
use Marble\JiraKpi\Domain\Model\Issue\IssueTransition;
use Marble\JiraKpi\Domain\Model\Issue\IssueType;
use Marble\JiraKpi\Domain\Model\Issue\Timeslot;
use Marble\JiraKpi\Domain\Model\Result\MonthlyBugCreation;
use Marble\JiraKpi\Domain\Model\Result\MonthlyBugFixingTime;
use Marble\JiraKpi\Domain\Model\Result\MonthlyBugLeadTime;
use Marble\JiraKpi\Domain\Model\Unit\Day;
use Marble\JiraKpi\Domain\Model\Unit\Second;
use Marble\JiraKpi\Domain\Model\Unit\StoryPoint;
use Marble\JiraKpi\Domain\Repository\Query\BugsReportedBetweenQuery;
use Marble\JiraKpi\Domain\Repository\Query\LatestTransitionQuery;
use Marble\JiraKpi\Domain\Repository\Query\TransitionedToStatusBetweenQuery;
use Marble\JiraKpi\Domain\Service\TimeslotCalculator;
use function Marble\JiraKpi\Domain\array_avg;

class BugsAnalyzer extends AbstractKpiCalculator
{
    public function __construct(
        private readonly EntityManager      $entityManager,
        private readonly TimeslotCalculator $timeslotCalculator,
    ) {
    }

    /**
     * @param int $numWholeMonths
     * @return list<MonthlyBugCreation>
     */
    public function calculateCreation(int $numWholeMonths): array
    {
        return $this->perMonth($numWholeMonths, function (CarbonImmutable $month): MonthlyBugCreation {
            /** @var list<Issue> $issues */
            $issues      = $this->entityManager->getRepository(Issue::class)->fetchMany(new BugsReportedBetweenQuery($month, $month->addMonth()));
            $created     = count($issues);
            $estimated   = count(array_filter($issues, fn(Issue $issue): bool => $issue->getEstimate() !== null));
            $storyPoints = new StoryPoint(array_sum(array_map(fn(Issue $issue): int => $issue->getEstimate()->value ?? 0, $issues)));
            $latencies   = [];

            foreach ($issues as $issue) {
                if ($causeKey = $issue->getCauseKey()) {
                    $latencies[$issue->getKey()] = (new Day(365))->toSecond(); // if we don't have the causing issue here, it's gonna be old

                    if ($cause = $this->entityManager->getRepository(Issue::class)->fetchOne(new SimpleId($causeKey))) {
                        $transition = $this->entityManager->getRepository(IssueTransition::class)->fetchOne(new LatestTransitionQuery($cause, IssueStatus::DONE));

                        if ($transition instanceof IssueTransition) {
                            $latencies[$issue->getKey()] = new Second($transition->getTransitioned()->diffInWeekdaySeconds($issue->getCreated()));
                        }
                    }
                }
            }

            return new MonthlyBugCreation(
                $month,
                $created,
                $estimated,
                $storyPoints,
                $latencies,
            );
        });
    }

    /**
     * @param int $numWholeMonths
     * @return list<MonthlyBugLeadTime>
     */
    public function calculateLeadTime(int $numWholeMonths): array
    {
        return $this->perMonth($numWholeMonths, function (CarbonImmutable $month): MonthlyBugLeadTime {
            $query  = new TransitionedToStatusBetweenQuery(IssueStatus::DONE, $month, $month->addMonth());
            $issues = $this->entityManager->getRepository(Issue::class)->fetchMany($query);
            /** @var list<Issue> $issues */
            $issues    = array_filter($issues, fn(Issue $issue): bool => $issue->getType() === IssueType::BUG);
            $fixed     = count($issues);
            $leadTimes = [];

            foreach ($issues as $issue) {
                $key        = $issue->getKey();
                $transition = $this->entityManager->getRepository(IssueTransition::class)->fetchOne(new LatestTransitionQuery($issue, IssueStatus::DONE));

                if ($transition instanceof IssueTransition) {
                    $leadTimes[$key] = new Second($issue->getCreated()->diffInWeekdaySeconds($transition->getTransitioned()));
                }
            }

            return new MonthlyBugLeadTime(
                $month,
                $fixed,
                $leadTimes,
            );
        });
    }

    public function calculateTimeFixingBugs(int $monthsBeforeLast): array
    {
        return $this->perMonth($monthsBeforeLast, function (CarbonImmutable $month): MonthlyBugFixingTime {
            $end         = $month->addMonth();
            $timeslots   = $this->timeslotCalculator->getTimeslotsOverlappingWith($month, $end);
            $activeTs    = array_filter($timeslots, fn(Timeslot $timeslot): bool => $timeslot->status->isActive() && $timeslot->issue->getType()->hasUsefulActiveTime());
            $onAny       = array_sum(array_map(fn(Timeslot $timeslot): int => $timeslot->getDurationBetween($month, $end)->value, $activeTs));
            $activeBugTs = array_filter($activeTs, fn(Timeslot $timeslot): bool => $timeslot->issue->getType() === IssueType::BUG);
            $onBugs      = array_sum(array_map(fn(Timeslot $timeslot): int => $timeslot->getDurationBetween($month, $end)->value, $activeBugTs));

            usort($activeBugTs, fn(Timeslot $a, Timeslot $b): int => $b->getDuration()->value <=> $a->getDuration()->value); // desc

            $slowest = array_slice($activeBugTs, 0, 3);

            return new MonthlyBugFixingTime(
                $month,
                new Second($onAny),
                new Second($onBugs),
                $slowest,
            );
        });
    }
}
