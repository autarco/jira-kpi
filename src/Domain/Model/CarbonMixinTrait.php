<?php

namespace Autarco\JiraKpi\Domain\Model;

use Carbon\CarbonInterface;

/**
 * @method float           diffInSeconds(CarbonInterface $date)
 * @method int             diffInWeekdays(CarbonInterface $date)
 * @method CarbonInterface endOfDay()
 * @method bool            isSameDay(CarbonInterface $date)
 * @method bool            isWeekDay()
 */
trait CarbonMixinTrait
{
    public function diffInWeekdaySeconds(CarbonInterface $date): float
    {
        if ($this->isSameDay($date)) {
            return $this->diffInSeconds($date);
        }

        $weekdays = $this->diffInWeekdays($date);

        if ($this->isWeekDay()) {
            $weekdays -= 1;
        }

        $seconds = $weekdays * CarbonInterface::SECONDS_PER_MINUTE * CarbonInterface::MINUTES_PER_HOUR * CarbonInterface::HOURS_PER_DAY;

        if ($this->isWeekDay()) {
            $seconds += $this->diffInSeconds($this->endOfDay());
        }

        if ($date->isWeekDay()) {
            $seconds += $date->startOfDay()->diffInSeconds($date);
        }

        return $seconds;

    }
}
