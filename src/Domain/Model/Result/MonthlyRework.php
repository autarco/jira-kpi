<?php

namespace Autarco\JiraKpi\Domain\Model\Result;

use Carbon\CarbonImmutable;
use Autarco\JiraKpi\Domain\Model\Issue\Issue;
use Autarco\JiraKpi\Domain\Model\Issue\IssueTransition;
use Autarco\JiraKpi\Domain\Model\Issue\IssueType;
use Autarco\JiraKpi\Domain\Model\Unit\StoryPoint;
use function Autarco\JiraKpi\Domain\div;

readonly class MonthlyRework
{
    /**
     * @param CarbonImmutable                $month
     * @param StoryPoint                     $velocity
     * @param list<Issue>                    $issues
     * @param array<string, IssueTransition> $causes
     */
    public function __construct(
        public CarbonImmutable $month,
        public StoryPoint      $velocity,
        public array           $issues,
        public array           $causes,
    ) {
    }

    public function getTotalUrgentRework(): StoryPoint
    {
        return $this->sumStoryPoints(...$this->issues);
    }

    public function getUrgentBugsFixed(): StoryPoint
    {
        return $this->sumStoryPoints(...array_filter($this->issues, fn(Issue $issue): bool => $issue->getType() === IssueType::BUG));
    }

    public function getUrgentGapsFixed(): StoryPoint
    {
        return $this->sumStoryPoints(...array_filter($this->issues, fn(Issue $issue): bool => $issue->getType() !== IssueType::BUG));
    }

    private function sumStoryPoints(Issue ...$issues): StoryPoint
    {
        return new StoryPoint(array_sum(array_map(fn(Issue $issue): int => $issue->getEstimate()?->value ?? 0, $issues)));
    }

    public function getFractionUrgentBugs(): float
    {
        return div($this->getUrgentBugsFixed()->value, $this->velocity->value);
    }

    public function getFractionUrgentGaps(): float
    {
        return div($this->getUrgentGapsFixed()->value, $this->velocity->value);
    }

    public function getFractionUrgentRework(): float
    {
        return div($this->getTotalUrgentRework()->value, $this->velocity->value);
    }

    public function getCause(Issue $issue): ?IssueTransition
    {
        return $this->causes[$issue->getKey()] ?? null;
    }
}
