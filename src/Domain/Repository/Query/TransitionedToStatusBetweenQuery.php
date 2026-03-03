<?php

namespace Autarco\JiraKpi\Domain\Repository\Query;

use Carbon\CarbonImmutable;
use Autarco\JiraKpi\Domain\Model\Issue\IssueStatus;

readonly class TransitionedToStatusBetweenQuery
{
    public function __construct(
        public IssueStatus     $status,
        public CarbonImmutable $after,
        public CarbonImmutable $before,
    ) {
    }
}
