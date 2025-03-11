<?php

namespace Marble\JiraKpi\Domain\Service\KpiCalculator;

use Carbon\CarbonImmutable;
use Marble\EntityManager\EntityManager;
use Marble\JiraKpi\Domain\Model\Issue\Issue;
use Marble\JiraKpi\Domain\Model\Issue\IssueStatus;
use Marble\JiraKpi\Domain\Model\Issue\IssueType;
use Marble\JiraKpi\Domain\Model\Result\MonthlyVelocity;
use Marble\JiraKpi\Domain\Model\Unit\StoryPoint;
use Marble\JiraKpi\Domain\Repository\Query\TransitionedToStatusBetweenQuery;

class VelocityCalculator extends AbstractKpiCalculator
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {
    }

    /**
     * @param int $numWholeMonths
     * @return list<MonthlyVelocity>
     */
    public function calculate(int $numWholeMonths): array
    {
        return $this->perMonth($numWholeMonths, function (CarbonImmutable $month): MonthlyVelocity {
            $query = new TransitionedToStatusBetweenQuery(IssueStatus::DONE, $month, $month->addMonth());
            /** @var list<Issue> $issues */
            $issues = $this->entityManager->getRepository(Issue::class)->fetchMany($query);
            /** @var array<string, StoryPoint> $storyPoints */
            $storyPoints = array_fill_keys(array_column(IssueType::cases(), 'name'), new StoryPoint(0));

            foreach ($issues as $issue) {
                $type               = $issue->getType()->name;
                $storyPoints[$type] = new StoryPoint($storyPoints[$type]->value + ($issue->getEstimate()?->value ?? 0));
            }

            return new MonthlyVelocity($month, $storyPoints);
        });
    }
}
