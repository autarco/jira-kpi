<?php

namespace Autarco\JiraKpi\Domain\Repository\Query;

use Carbon\CarbonImmutable;

readonly class TransitionedBetweenQuery
{
    public function __construct(
        public CarbonImmutable $after,
        public CarbonImmutable $before,
    ) {
    }
}
