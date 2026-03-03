<?php

namespace Autarco\JiraKpi\Domain\Repository\Query;

use Autarco\JiraKpi\Domain\Model\Issue\Issue;
use Autarco\JiraKpi\Domain\Model\Issue\IssueStatus;

readonly class LatestTransitionQuery
{
    public function __construct(
        public Issue       $issue,
        public IssueStatus $to,
    ) {
    }
}
