<?php

namespace Autarco\JiraKpi\Domain\Repository\Query;

use Carbon\CarbonImmutable;

readonly class BugsReportedBetweenQuery
{
    public function __construct(
        public CarbonImmutable $after,
        public CarbonImmutable $before,
    ) {
    }
}
