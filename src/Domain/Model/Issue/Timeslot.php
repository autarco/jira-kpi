<?php

namespace Marble\JiraKpi\Domain\Model\Issue;

use Carbon\CarbonImmutable;
use Marble\JiraKpi\Domain\Model\Unit\Second;

readonly class Timeslot
{
    public function __construct(
        public Issue            $issue,
        public IssueStatus      $status,
        public CarbonImmutable  $from,
        public ?CarbonImmutable $to,
    ) {
    }

    public function getDuration(): ?Second
    {
        return $this->to ? new Second($this->from->diffInWeekdaySeconds($this->to)) : null;
    }

    public function getDurationBetween(CarbonImmutable $start, CarbonImmutable $end): Second
    {
        $from = $start->max($this->from);
        $to   = $end->min($this->to);

        return new Second($from->diffInWeekdaySeconds($to));
    }
}
