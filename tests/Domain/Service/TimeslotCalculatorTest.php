<?php

namespace Autarco\JiraKpi\Tests\Domain\Service;

use Carbon\CarbonImmutable;
use Marble\EntityManager\EntityManager;
use Marble\EntityManager\Repository\Repository;
use Autarco\JiraKpi\Domain\Model\Issue\Issue;
use Autarco\JiraKpi\Domain\Model\Issue\IssueStatus;
use Autarco\JiraKpi\Domain\Model\Issue\IssueTransition;
use Autarco\JiraKpi\Domain\Model\Issue\Timeslot;
use Autarco\JiraKpi\Domain\Repository\Query\FirstTransitionAfterQuery;
use Autarco\JiraKpi\Domain\Repository\Query\LastTransitionBeforeQuery;
use Autarco\JiraKpi\Domain\Repository\Query\TransitionedBetweenQuery;
use Autarco\JiraKpi\Domain\Service\TimeslotCalculator;
use Autarco\JiraKpi\Tests\AbstractTestCase;
use Mockery;
use Mockery\MockInterface;

class TimeslotCalculatorTest extends AbstractTestCase
{
    public function testCalculateTimeslotsForIssue(): void
    {
        $entityManager = mock(EntityManager::class);
        $repository    = mock(Repository::class);
        $issue         = mock(Issue::class);

        $issue->allows()->getCreated()->andReturn(new CarbonImmutable('2024-10-02 15:00:00'));
        $entityManager->allows()->getRepository(IssueTransition::class)->andReturn($repository);
        $repository->shouldReceive('fetchManyBy')->andReturn([
            $this->mockTransition('2024-10-07 09:30:00', IssueStatus::TO_DO, IssueStatus::IN_PROGRESS),
            $this->mockTransition('2024-10-09 12:00:00', IssueStatus::IN_PROGRESS, IssueStatus::PENDING_TR),
            $this->mockTransition('2024-10-10 16:30:00', IssueStatus::PENDING_TR, IssueStatus::TECH_REVIEW),
            $this->mockTransition('2024-10-10 17:00:00', IssueStatus::TECH_REVIEW, IssueStatus::PENDING_FR),
            $this->mockTransition('2024-10-14 11:00:00', IssueStatus::PENDING_FR, IssueStatus::FUNCTIONAL_REVIEW),
            $this->mockTransition('2024-10-14 15:00:00', IssueStatus::FUNCTIONAL_REVIEW, IssueStatus::PENDING_RELEASE),
            $this->mockTransition('2024-10-15 10:00:00', IssueStatus::PENDING_RELEASE, IssueStatus::DONE),
        ]);

        $timeslotCalculator = new TimeslotCalculator($entityManager);
        $timeslots          = $timeslotCalculator->calculateTimeslots($issue);

        $this->assertIsArray($timeslots);
        $this->assertTrue(array_is_list($timeslots));
        $this->assertCount(8, $timeslots);
        $this->assertInstanceOf(Timeslot::class, $timeslots[0]);
        $this->assertInstanceOf(Timeslot::class, $timeslots[7]);
        $this->assertSame($issue, $timeslots[0]->issue);
        $this->assertSame($issue, $timeslots[7]->issue);
        $this->assertEquals(IssueStatus::TO_DO, $timeslots[0]->status);
        $this->assertEquals(IssueStatus::IN_PROGRESS, $timeslots[1]->status);
        $this->assertEquals(IssueStatus::PENDING_TR, $timeslots[2]->status);
        $this->assertEquals(IssueStatus::TECH_REVIEW, $timeslots[3]->status);
        $this->assertEquals(IssueStatus::PENDING_FR, $timeslots[4]->status);
        $this->assertEquals(IssueStatus::FUNCTIONAL_REVIEW, $timeslots[5]->status);
        $this->assertEquals(IssueStatus::PENDING_RELEASE, $timeslots[6]->status);
        $this->assertEquals(IssueStatus::DONE, $timeslots[7]->status);
        $this->assertEqualsWithDelta(3600 * (24 + 24 + 18.5), $timeslots[0]->getDuration()->value, .001);
        $this->assertEqualsWithDelta(3600 * (24 + 24 + 2.5), $timeslots[1]->getDuration()->value, .001);
        $this->assertEqualsWithDelta(3600 * (24 + 4.5), $timeslots[2]->getDuration()->value, .001);
        $this->assertEqualsWithDelta(3600 * .5, $timeslots[3]->getDuration()->value, .001);
        $this->assertEqualsWithDelta(3600 * (24 + 18), $timeslots[4]->getDuration()->value, .001);
        $this->assertEqualsWithDelta(3600 * 4, $timeslots[5]->getDuration()->value, .001);
        $this->assertEqualsWithDelta(3600 * 19, $timeslots[6]->getDuration()->value, .001);
        $this->assertNull($timeslots[7]->to);
    }

    public function testCalculateOneIssuesOverlappingTimeslotsWithoutBefore(): void
    {
        $entityManager = mock(EntityManager::class);
        $repository    = mock(Repository::class);
        $issue         = $this->mockIssue('A', '2024-09-29 20:00:00');
        $during        = [
            $this->mockTransition('2024-10-02 10:00:00', IssueStatus::TO_DO, IssueStatus::IN_PROGRESS, $issue),
            $this->mockTransition('2024-10-10 12:00:00', IssueStatus::IN_PROGRESS, IssueStatus::PENDING_TR, $issue),
        ];

        $after = [
            $this->mockTransition('2024-11-01 06:00:00', IssueStatus::PENDING_TR, IssueStatus::TECH_REVIEW, $issue),
        ];

        $start = new CarbonImmutable('2024-10-01');
        $end   = $start->addMonth();

        $entityManager->allows()->getRepository(IssueTransition::class)->andReturn($repository);
        $repository->shouldReceive('fetchMany')->with(Mockery::type(TransitionedBetweenQuery::class))->andReturn($during);
        $repository->shouldReceive('fetchMany')->with(Mockery::type(LastTransitionBeforeQuery::class))->andReturn([]);
        $repository->shouldReceive('fetchMany')->with(Mockery::type(FirstTransitionAfterQuery::class))->andReturn($after);

        $timeslotCalculator = new TimeslotCalculator($entityManager);
        $timeslots          = $timeslotCalculator->getTimeslotsOverlappingWith($start, $end);

        $this->assertIsArray($timeslots);
        $this->assertTrue(array_is_list($timeslots));
        $this->assertCount(3, $timeslots);
        $this->assertInstanceOf(Timeslot::class, $timeslots[0]);
        $this->assertInstanceOf(Timeslot::class, $timeslots[2]);
        $this->assertEquals(IssueStatus::TO_DO, $timeslots[0]->status);
        $this->assertEquals(IssueStatus::IN_PROGRESS, $timeslots[1]->status);
        $this->assertEquals(IssueStatus::PENDING_TR, $timeslots[2]->status);
        $this->assertEqualsWithDelta(3600 * (24 + 24 + 10), $timeslots[0]->getDuration()->value, .001);
        $this->assertEqualsWithDelta(3600 * (24 + 10), $timeslots[0]->getDurationBetween($start, $end)->value, .001);
        $this->assertEqualsWithDelta(3600 * ((24 * 6) + 2), $timeslots[1]->getDuration()->value, .001);
        $this->assertEqualsWithDelta(3600 * (24 * 15.75), $timeslots[2]->getDuration()->value, .001);
        $this->assertEqualsWithDelta(3600 * (24 * 15.5), $timeslots[2]->getDurationBetween($start, $end)->value, .001);
    }

    private function mockTransition(string $transitioned, IssueStatus $from, IssueStatus $to, ?Issue $issue = null): MockInterface & IssueTransition
    {
        $transition = mock(IssueTransition::class);

        $transition->allows()->getTransitioned()->andReturn(CarbonImmutable::make($transitioned));
        $transition->allows()->getFrom()->andReturn($from);
        $transition->allows()->getTo()->andReturn($to);

        if ($issue) {
            $transition->allows()->getIssue()->andReturn($issue);
        }

        return $transition;
    }
}
