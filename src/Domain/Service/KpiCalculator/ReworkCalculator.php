<?php

namespace Marble\JiraKpi\Domain\Service\KpiCalculator;

use Carbon\CarbonImmutable;
use Marble\Entity\SimpleId;
use Marble\EntityManager\EntityManager;
use Marble\JiraKpi\Domain\Model\Issue\Issue;
use Marble\JiraKpi\Domain\Model\Issue\IssueStatus;
use Marble\JiraKpi\Domain\Model\Issue\IssueTransition;
use Marble\JiraKpi\Domain\Model\Result\MonthlyRework;
use Marble\JiraKpi\Domain\Model\Unit\Day;
use Marble\JiraKpi\Domain\Model\Unit\StoryPoint;
use Marble\JiraKpi\Domain\Repository\Query\LatestTransitionQuery;
use Marble\JiraKpi\Domain\Repository\Query\TransitionedToStatusBetweenQuery;

class ReworkCalculator extends AbstractKpiCalculator
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {
    }

    public function calculate(int $numWholeMonths, Day $tooSoon): array
    {
        return $this->perMonth($numWholeMonths, function (CarbonImmutable $month) use ($tooSoon): MonthlyRework {
            return $this->calculateMonth($month, $tooSoon);
        });
    }

    public function calculateMonth(CarbonImmutable $month, Day $tooSoon): MonthlyRework
    {
        $query = new TransitionedToStatusBetweenQuery(IssueStatus::DONE, $month, $month->addMonth());
        /** @var list<Issue> $issues */
        $issues       = $this->entityManager->getRepository(Issue::class)->fetchMany($query);
        $velocity     = new StoryPoint(array_sum(array_map(fn(Issue $issue): int => $issue->getEstimate()?->value ?? 0, $issues)));
        $urgentIssues = [];
        $causes       = [];

        foreach ($issues as $issue) {
            if ($causeKey = $issue->getCauseKey()) {
                if ($cause = $this->entityManager->getRepository(Issue::class)->fetchOne(new SimpleId($causeKey))) {
                    $transition = $this->entityManager->getRepository(IssueTransition::class)->fetchOne(new LatestTransitionQuery($cause, IssueStatus::DONE));

                    if ($transition instanceof IssueTransition) {
                        $latency = $transition->getTransitioned()->diffInWeekdaySeconds($issue->getCreated());

                        if ($latency < $tooSoon->toSecond()->value) {
                            $urgentIssues[]           = $issue;
                            $causes[$issue->getKey()] = $transition;
                        }
                    }
                }
            }
        }

        return new MonthlyRework(
            $month,
            $velocity,
            $urgentIssues,
            $causes,
        );
    }
}
