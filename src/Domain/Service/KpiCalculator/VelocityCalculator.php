<?php

namespace Marble\JiraKpi\Domain\Service\KpiCalculator;

use Carbon\CarbonImmutable;
use Marble\EntityManager\EntityManager;
use Marble\JiraKpi\Domain\Model\Issue\Issue;
use Marble\JiraKpi\Domain\Model\Issue\IssueStatus;
use Marble\JiraKpi\Domain\Model\Issue\IssueType;
use Marble\JiraKpi\Domain\Model\Issue\WorkCategory;
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
            /** @var array<string, StoryPoint> $spPerType */
            $spPerType = array_fill_keys(array_column(IssueType::cases(), 'name'), new StoryPoint(0));
            /** @var array<string, StoryPoint> $spPerCategory */
            $spPerCategory = array_fill_keys(array_column(WorkCategory::cases(), 'name'), new StoryPoint(0));

            foreach ($issues as $issue) {
                $type                     = $issue->getType()->name;
                $spPerType[$type]         = new StoryPoint($spPerType[$type]->value + ($issue->getEstimate()?->value ?? 0));
                $category                 = $issue->getCategory()->name;
                $spPerCategory[$category] = new StoryPoint($spPerCategory[$category]->value + ($issue->getEstimate()?->value ?? 0));
            }

            unset($spPerType[IssueType::EPIC->name]);

            return new MonthlyVelocity($month, $spPerType, $spPerCategory);
        });
    }
}
