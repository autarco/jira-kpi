<?php

namespace Marble\JiraKpi\Domain\Service\KpiCalculator;

use Carbon\CarbonImmutable;
use Marble\EntityManager\EntityManager;
use Marble\JiraKpi\Domain\Model\Issue\Issue;
use Marble\JiraKpi\Domain\Model\Issue\IssueStatus;
use Marble\JiraKpi\Domain\Model\Issue\IssueTransition;
use Marble\JiraKpi\Domain\Model\Issue\IssueType;
use Marble\JiraKpi\Domain\Model\Issue\Timeslot;
use Marble\JiraKpi\Domain\Model\Result\MonthlyCycleTime;
use Marble\JiraKpi\Domain\Model\Result\MonthlyDevIterations;
use Marble\JiraKpi\Domain\Model\Result\MonthlyTimePendingRelease;
use Marble\JiraKpi\Domain\Model\Unit\Second;
use Marble\JiraKpi\Domain\Repository\Query\EarliestTransitionQuery;
use Marble\JiraKpi\Domain\Repository\Query\LatestTransitionQuery;
use Marble\JiraKpi\Domain\Repository\Query\TransitionedFromStatusBetweenQuery;
use Marble\JiraKpi\Domain\Repository\Query\TransitionedToStatusBetweenQuery;
use Marble\JiraKpi\Domain\Service\TimeslotCalculator;
use function Marble\JiraKpi\Domain\array_avg;

class DevEfficiencyCalculator extends AbstractKpiCalculator
{
    public function __construct(
        private readonly EntityManager      $entityManager,
        private readonly TimeslotCalculator $timeslotCalculator,
    ) {
    }

    /**
     * @param int $numWholeMonths
     * @return list<MonthlyCycleTime>
     */
    public function calculateCycleTime(int $numWholeMonths): array
    {
        return $this->perMonth($numWholeMonths, function (CarbonImmutable $month): MonthlyCycleTime {
            $query = new TransitionedToStatusBetweenQuery(IssueStatus::DONE, $month, $month->addMonth());
            /** @var list<Issue> $issues */
            $issues         = $this->entityManager->getRepository(Issue::class)->fetchMany($query);
            $issues         = array_filter($issues, fn(Issue $issue): bool => $issue->getType() !== IssueType::EPIC);
            $transitionRepo = $this->entityManager->getRepository(IssueTransition::class);
            $done           = count($issues);
            $cycleTimes     = [];

            foreach ($issues as $issue) {
                $startTransition = $transitionRepo->fetchOne(new EarliestTransitionQuery($issue, IssueStatus::IN_PROGRESS));
                $endStatus       = $issue->getType() === IssueType::STORY ? IssueStatus::PENDING_AT : IssueStatus::DONE;
                $endTransition   = $transitionRepo->fetchOne(new LatestTransitionQuery($issue, $endStatus));

                if ($startTransition instanceof IssueTransition && $endTransition instanceof IssueTransition) {
                    $cycleTimes[$issue->getKey()] = new Second($startTransition->getTransitioned()->diffInWeekdaySeconds($endTransition->getTransitioned()));
                }
            }

            return new MonthlyCycleTime($month, $done, $cycleTimes);
        });
    }

    /**
     * @param int $numWholeMonths
     * @return list<MonthlyDevIterations>
     */
    public function calculateDevIterations(int $numWholeMonths): array
    {
        return $this->perMonth($numWholeMonths, function (CarbonImmutable $month): MonthlyDevIterations {
            $query = new TransitionedFromStatusBetweenQuery(IssueStatus::IN_PROGRESS, $month, $month->addMonth());
            /** @var list<IssueTransition> $transitions */
            $transitions = $this->entityManager->getRepository(IssueTransition::class)->fetchMany($query);
            $tickets     = [];

            foreach ($transitions as $transition) {
                $key = $transition->getIssue()->getKey();

                if (!array_key_exists($key, $tickets)) {
                    $tickets[$key] = 0;
                }

                $tickets[$key]++;
            }

            arsort($tickets);

            $mostIterated = array_slice($tickets, 0, 3, true);

            return new MonthlyDevIterations(
                $month,
                count($tickets),
                count($transitions),
                count(array_filter($tickets, fn(int $num): bool => $num === 1)),
                count(array_filter($tickets, fn(int $num): bool => $num === 2)),
                $mostIterated,
            );
        });
    }

    /**
     * @param int $numWholeMonths
     * @return list<MonthlyDevIterations>
     */
    public function calculateDevIterationsUsingFeedbackToProcess(int $numWholeMonths): array
    {
        return $this->perMonth($numWholeMonths, function (CarbonImmutable $month): MonthlyDevIterations {
            $query = new TransitionedFromStatusBetweenQuery(IssueStatus::IN_PROGRESS, $month, $month->addMonth());
            /** @var list<IssueTransition> $transitions */
            $transitions = $this->entityManager->getRepository(IssueTransition::class)->fetchMany($query);
            $tickets     = [];

            foreach ($transitions as $transition) {
                $tickets[$transition->getIssue()->getKey()] = 1;
            }

            $query = new TransitionedToStatusBetweenQuery(IssueStatus::FEEDBACK_TO_PROCESS, $month, $month->addMonth());
            /** @var list<IssueTransition> $transitions */
            $transitions = $this->entityManager->getRepository(IssueTransition::class)->fetchMany($query);

            foreach ($transitions as $transition) {
                $key = $transition->getIssue()->getKey();

                if (!array_key_exists($key, $tickets)) {
                    $tickets[$key] = 1;
                }

                $tickets[$key]++;
            }

            arsort($tickets);

            $mostIterated = array_slice($tickets, 0, 3, true);

            return new MonthlyDevIterations(
                $month,
                count($tickets),
                array_sum($tickets),
                count(array_filter($tickets, fn(int $num): bool => $num === 1)),
                count(array_filter($tickets, fn(int $num): bool => $num === 2)),
                $mostIterated,
            );
        });
    }

    /**
     * @param int $numWholeMonths
     * @return list<MonthlyTimePendingRelease>
     */
    public function calculateTimePendingRelease(int $numWholeMonths): array
    {
        return $this->perMonth($numWholeMonths, function (CarbonImmutable $month): MonthlyTimePendingRelease {
            $end            = $month->addMonth();
            $timeslots      = $this->timeslotCalculator->getTimeslotsOverlappingWith($month, $end);
            $tsPendingRel   = array_filter($timeslots, fn(Timeslot $timeslot): bool => $timeslot->issue->getType() !== IssueType::EPIC && $timeslot->status === IssueStatus::PENDING_RELEASE);
            $timePendingRel = array_sum(array_map(fn(Timeslot $timeslot): int => $timeslot->getDurationBetween($month, $end)->value, $tsPendingRel));

            usort($tsPendingRel, fn(Timeslot $a, Timeslot $b): int => $b->getDuration()->value <=> $a->getDuration()->value);

            $slowest = array_slice($tsPendingRel, 0, 3);

            return new MonthlyTimePendingRelease(
                $month,
                count($tsPendingRel),
                new Second($timePendingRel),
                $slowest,
            );
        });
    }
}
