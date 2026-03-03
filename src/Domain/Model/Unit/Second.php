<?php

namespace Autarco\JiraKpi\Domain\Model\Unit;

readonly class Second extends Unit
{
    public const int SECONDS_IN_HOUR         = 60 * 60;
    public const int SECONDS_IN_DAY          = self::SECONDS_IN_HOUR * 24;
    public const int WEEKDAY_SECONDS_IN_WEEK = 60 * 60 * 24 * 5;

    public function toHour(): Hour
    {
        return new Hour($this->value / self::SECONDS_IN_HOUR);
    }

    public function toDay(): Day
    {
        return new Day($this->value / self::SECONDS_IN_DAY);
    }
}
