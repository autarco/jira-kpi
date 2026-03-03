<?php

namespace Autarco\JiraKpi\Domain\Model\Result;

use Carbon\CarbonImmutable;
use Autarco\JiraKpi\Domain\Model\Issue\Issue;
use Autarco\JiraKpi\Domain\Model\Issue\IssueStatus;
use Autarco\JiraKpi\Domain\Model\Issue\Timeslot;
use Autarco\JiraKpi\Domain\Model\Unit\Second;
use Autarco\JiraKpi\Domain\Model\Unit\StoryPoint;
use function Autarco\JiraKpi\Domain\div;

readonly class MonthlyWaitingTime
{
    /**
     * @param CarbonImmutable               $month
     * @param array<string, list<Timeslot>> $timeslotsPerIssue by issue key
     * @param list<Issue>                   $issues
     */
    public function __construct(
        public CarbonImmutable $month,
        private array          $timeslotsPerIssue,
        private array          $issues,
    ) {
    }

    public function getIssues(): array
    {
        return $this->issues;
    }

    /**
     * @return list<Timeslot>
     */
    public function getTimeslots(Issue $issue, ?callable $filter = null): array
    {
        $timeslots = $this->timeslotsPerIssue[$issue->getKey()] ?? [];

        if ($filter !== null) {
            $timeslots = array_filter($timeslots, $filter);
        }

        return $timeslots;
    }

    public function getAvgTimeInStatus(IssueStatus $status, bool $perStoryPoint = false): Second
    {
        return $this->getAvgTime(fn(Timeslot $slot): bool => $slot->status === $status, $perStoryPoint);
    }

    public function getAvgWaitingTime(bool $perStoryPoint = false): Second
    {
        return $this->getAvgTime(fn(Timeslot $slot): bool => $slot->status->isWaiting(), $perStoryPoint);
    }

    public function getAvgActiveTime(bool $perStoryPoint = false): Second
    {
        return $this->getAvgTime(fn(Timeslot $slot): bool => $slot->status->isActive(false), $perStoryPoint);
    }

    public function getAvgCycleTime(bool $perStoryPoint = false): Second
    {
        return $this->getAvgTime(fn(Timeslot $slot): bool => $slot->status->isCycle(), $perStoryPoint);
    }

    private function getAvgTime(callable $filter, bool $perStoryPoint): Second
    {
        $second = 0;

        foreach ($this->issues as $issue) {
            $timeslots = $this->getTimeslots($issue, $filter);
            $second    += $this->sumDuration(...$timeslots)->value;
        }

        $denominator = $perStoryPoint ? $this->getTotalStoryPoints()->value : count($this->issues);

        return new Second(div($second, $denominator));
    }

    private function sumDuration(Timeslot ...$timeslots): Second
    {
        return new Second(array_sum(array_map(fn(Timeslot $slot): int => $slot->getDuration()?->value ?? 0, $timeslots)));
    }

    public function getTotalStoryPoints(): StoryPoint
    {
        return new StoryPoint(array_sum(array_map(fn(Issue $issue): int => $issue->getEstimate()?->value ?? 0, $this->issues)));
    }
}
