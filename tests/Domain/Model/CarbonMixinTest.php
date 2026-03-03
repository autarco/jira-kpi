<?php

namespace Domain\Model;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Autarco\JiraKpi\Domain\Model\CarbonMixinTrait;
use Autarco\JiraKpi\Tests\AbstractTestCase;

class CarbonMixinTest extends AbstractTestCase
{
    private const int SECONDS_IN_HOUR = 60 * 60;

    public static function setUpBeforeClass(): void
    {
        if (!CarbonImmutable::hasMacro('diffInWeekdaySeconds')) {
            CarbonImmutable::mixin(CarbonMixinTrait::class);
        }
    }

    public function testThatCarbonDiffInWeekdaysCountsNumberOfOvernightsExcludingSatToSundayAndSunToMonday(): void
    {
        $start = new CarbonImmutable('2024-10-14 15:00:00');

        $this->assertEquals(0, $start->diffInWeekdays(new CarbonImmutable('2024-10-14 23:59:59')));
        $this->assertEquals(1, $start->diffInWeekdays(new CarbonImmutable('2024-10-15 00:00:00'))); // Mon -> Tue
        $this->assertEquals(1, $start->diffInWeekdays(new CarbonImmutable('2024-10-15 14:59:59')));
        $this->assertEquals(1, $start->diffInWeekdays(new CarbonImmutable('2024-10-15 15:00:00')));
        $this->assertEquals(1, $start->diffInWeekdays(new CarbonImmutable('2024-10-15 15:00:01')));
        $this->assertEquals(2, $start->diffInWeekdays(new CarbonImmutable('2024-10-16 00:00:00'))); // Tue -> Wed
        $this->assertEquals(2, $start->diffInWeekdays(new CarbonImmutable('2024-10-16 14:59:59')));
        $this->assertEquals(2, $start->diffInWeekdays(new CarbonImmutable('2024-10-16 15:00:00')));
        $this->assertEquals(2, $start->diffInWeekdays(new CarbonImmutable('2024-10-16 15:00:01')));
        $this->assertEquals(3, $start->diffInWeekdays(new CarbonImmutable('2024-10-17 23:59:59')));
        $this->assertEquals(4, $start->diffInWeekdays(new CarbonImmutable('2024-10-18 00:00:00'))); // Thu --> Fri
        $this->assertEquals(4, $start->diffInWeekdays(new CarbonImmutable('2024-10-18 23:59:59')));
        $this->assertEquals(5, $start->diffInWeekdays(new CarbonImmutable('2024-10-19 00:00:00'))); // Fri -> Sat
        $this->assertEquals(5, $start->diffInWeekdays(new CarbonImmutable('2024-10-19 23:59:59')));
        $this->assertEquals(5, $start->diffInWeekdays(new CarbonImmutable('2024-10-20 00:00:00'))); // Sat -> Sun
        $this->assertEquals(5, $start->diffInWeekdays(new CarbonImmutable('2024-10-20 23:59:59')));
        $this->assertEquals(5, $start->diffInWeekdays(new CarbonImmutable('2024-10-21 00:00:00'))); // Sun -> Mon
        $this->assertEquals(5, $start->diffInWeekdays(new CarbonImmutable('2024-10-21 14:59:59')));
        $this->assertEquals(5, $start->diffInWeekdays(new CarbonImmutable('2024-10-21 15:00:00')));
        $this->assertEquals(5, $start->diffInWeekdays(new CarbonImmutable('2024-10-21 15:00:01')));
        $this->assertEquals(5, $start->diffInWeekdays(new CarbonImmutable('2024-10-21 23:59:59')));
        $this->assertEquals(6, $start->diffInWeekdays(new CarbonImmutable('2024-10-22 00:00:00'))); // Mon -> Tue

        $start = new CarbonImmutable('2024-10-15 00:00:00');

        $this->assertEquals(0, $start->diffInWeekdays(new CarbonImmutable('2024-10-15 01:01:01')));
        $this->assertEquals(0, $start->diffInWeekdays(new CarbonImmutable('2024-10-15 23:59:59')));
        $this->assertEquals(1, $start->diffInWeekdays(new CarbonImmutable('2024-10-16 00:00:00'))); // Tue -> Wed
        $this->assertEquals(3, $start->diffInWeekdays(new CarbonImmutable('2024-10-18 23:59:59')));
        $this->assertEquals(4, $start->diffInWeekdays(new CarbonImmutable('2024-10-19 00:00:00'))); // Fri -> Sat
        $this->assertEquals(4, $start->diffInWeekdays(new CarbonImmutable('2024-10-19 23:59:59')));
        $this->assertEquals(4, $start->diffInWeekdays(new CarbonImmutable('2024-10-20 00:00:00'))); // Sat -> Sun
        $this->assertEquals(4, $start->diffInWeekdays(new CarbonImmutable('2024-10-20 23:59:59')));
        $this->assertEquals(4, $start->diffInWeekdays(new CarbonImmutable('2024-10-21 00:00:00'))); // Sun -> Mon
        $this->assertEquals(4, $start->diffInWeekdays(new CarbonImmutable('2024-10-21 23:59:59')));
        $this->assertEquals(5, $start->diffInWeekdays(new CarbonImmutable('2024-10-22 00:00:00'))); // Mon -> Tue
    }

    public function testDiffInWeekdaySecondsWithinOneWeek(): void
    {
        $start = new CarbonImmutable('2024-10-16 15:00:00');

        $this->assertEqualsWithDelta(self::SECONDS_IN_HOUR * 4.5, $start->diffInWeekdaySeconds($end = new CarbonImmutable('2024-10-16 19:30:00')), .001);
        $this->assertEqualsWithDelta($start->diffInSeconds($end), $start->diffInWeekdaySeconds($end), .001);
        $this->assertEqualsWithDelta($start->diffInSeconds($end = new CarbonImmutable('2024-10-17 10:30:00')), $start->diffInWeekdaySeconds($end), .001);
        $this->assertEqualsWithDelta($start->diffInSeconds($end = new CarbonImmutable('2024-10-17 23:59:59')), $start->diffInWeekdaySeconds($end), .001);
        $this->assertEqualsWithDelta($start->diffInSeconds($end = new CarbonImmutable('2024-10-18 00:00:00')), $start->diffInWeekdaySeconds($end), .001);
        $this->assertEqualsWithDelta($start->diffInSeconds($end = new CarbonImmutable('2024-10-18 23:59:59')), $start->diffInWeekdaySeconds($end), .001);

        $secondsTillWeekend = $start->diffInSeconds(new CarbonImmutable('2024-10-19 00:00:00'));

        $this->assertEqualsWithDelta($secondsTillWeekend, $start->diffInWeekdaySeconds(new CarbonImmutable('2024-10-19 00:00:00')), .001);
        $this->assertEqualsWithDelta($secondsTillWeekend, $start->diffInWeekdaySeconds(new CarbonImmutable('2024-10-19 23:59:59')), .001);
        $this->assertEqualsWithDelta($secondsTillWeekend, $start->diffInWeekdaySeconds(new CarbonImmutable('2024-10-20 00:00:00')), .001);
        $this->assertEqualsWithDelta($secondsTillWeekend, $start->diffInWeekdaySeconds(new CarbonImmutable('2024-10-20 23:59:59')), .001);
    }

    public function testDiffInWeekdaySecondsAcrossOneWeekend(): void
    {
        $start = new CarbonImmutable('2024-10-16 15:00:00');
        $test  = function (CarbonImmutable $end, int $numWeekends) use ($start): void {
            $secondsInWeekends = $numWeekends * (self::SECONDS_IN_HOUR * 24 * 2);

            $this->assertEqualsWithDelta($start->diffInSeconds($end) - $secondsInWeekends, $start->diffInWeekdaySeconds($end), .001);
        };

        $test(new CarbonImmutable('2024-10-21 00:00:00'), 1);
        $test(new CarbonImmutable('2024-10-21 10:00:00'), 1);
        $test(new CarbonImmutable('2024-10-21 23:59:59'), 1);
        $test(new CarbonImmutable('2024-10-22 00:00:00'), 1);
        $test(new CarbonImmutable('2024-10-23 17:00:00'), 1);
        $test(new CarbonImmutable('2024-10-25 22:00:00'), 1);
        $test(new CarbonImmutable('2024-10-30 02:00:00'), 2);
    }

    public function testDiffInWeekdaySecondsFromWeekend(): void
    {
        $start = new CarbonImmutable('2024-10-12 12:00:00');

        $this->assertEqualsWithDelta(0, $start->diffInWeekdaySeconds(new CarbonImmutable('2024-10-13 18:00:00')), .001);
        $this->assertEqualsWithDelta(1, $start->diffInWeekdaySeconds(new CarbonImmutable('2024-10-14 00:00:01')), .001);
    }
}
