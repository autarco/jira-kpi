<?php

namespace Autarco\JiraKpi\Domain\Service;

use Carbon\CarbonImmutable;
use Marble\EntityManager\EntityManager;
use Autarco\JiraKpi\Domain\Model\Issue\Issue;
use Autarco\JiraKpi\Domain\Model\Issue\IssueTransition;
use Autarco\JiraKpi\Domain\Model\Issue\Timeslot;
use Autarco\JiraKpi\Domain\Repository\Query\FirstTransitionAfterQuery;
use Autarco\JiraKpi\Domain\Repository\Query\LastTransitionBeforeQuery;
use Autarco\JiraKpi\Domain\Repository\Query\TransitionedBetweenQuery;

class TimeslotCalculator
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {
    }

    /**
     * @param CarbonImmutable $start
     * @param CarbonImmutable $end
     * @return list<Timeslot>
     */
    public function getTimeslotsOverlappingWith(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $transitionRepo = $this->entityManager->getRepository(IssueTransition::class);
        $during         = $transitionRepo->fetchMany(new TransitionedBetweenQuery($start, $end));
        $before         = $transitionRepo->fetchMany(new LastTransitionBeforeQuery($start));
        /** @var array<string, IssueTransition> $before */
        $before = array_combine(array_map(fn(IssueTransition $t): string => $t->getIssue()->getKey(), $before), $before);
        $after  = $transitionRepo->fetchMany(new FirstTransitionAfterQuery($end));
        /** @var list<IssueTransition> $transitions */
        $transitions = [...$during, ...$after];
        /** @var array<string, list<Timeslot>> $timeslots */
        $timeslots = [];

        foreach ($transitions as $transition) {
            $issue = $transition->getIssue();
            $key   = $issue->getKey();

            if (count($timeslots[$key] ?? []) > 0) {
                $timeslotStart = $timeslots[$key][count($timeslots[$key]) - 1]->to;
            } elseif (isset($before[$key])) {
                $timeslotStart = $before[$key]->getTransitioned();
            } else {
                $timeslotStart = $issue->getCreated();
            }

            $timeslots[$key][] = new Timeslot(
                $issue,
                $transition->getFrom(),
                $timeslotStart,
                $transition->getTransitioned(),
            );
        }

        return array_merge(...array_values($timeslots));
    }

    /**
     * @param Issue $issue
     * @return list<Timeslot>
     */
    public function calculateTimeslots(Issue $issue): array
    {
        /** @var list<IssueTransition> $transitions */
        $transitions = $this->entityManager->getRepository(IssueTransition::class)->fetchManyBy(['issue' => $issue]);
        $timeslots   = [];
        $previous    = null;

        usort($transitions, fn(IssueTransition $a, IssueTransition $b): int => $a->getTransitioned() <=> $b->getTransitioned());

        foreach ($transitions as $transition) {
            $timeslots[] = new Timeslot(
                $issue,
                $transition->getFrom(),
                $previous?->getTransitioned() ?? $issue->getCreated(),
                $transition->getTransitioned(),
            );

            $previous = $transition;
        }

        if ($previous instanceof IssueTransition) {
            $timeslots[] = new Timeslot(
                $issue,
                $previous->getTo(),
                $previous->getTransitioned(),
                null
            );
        }

        return $timeslots;
    }
}
