<?php

namespace Marble\JiraKpi\Domain\Model\Unit;

readonly class Hour extends Unit
{
    public function toSecond(): Second
    {
        return new Second($this->value * Second::SECONDS_IN_HOUR);
    }

    public function toDay(): Day
    {
        return $this->toSecond()->toDay();
    }
}
