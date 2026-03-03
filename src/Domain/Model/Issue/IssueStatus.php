<?php

namespace Autarco\JiraKpi\Domain\Model\Issue;

enum IssueStatus
{
    case TO_DO; // on the backlog
    case SELECTED_FOR_DEV; // on the board
    case FEEDBACK_TO_PROCESS;
    case IN_PROGRESS;
    case PENDING_TR;
    case TECH_REVIEW;
    case PENDING_FR;
    case FUNCTIONAL_REVIEW;
    case PENDING_AT;
    case ACCEPTANCE_TESTING;
    case PENDING_RELEASE;
    case DONE;
    case CANCELLED;

    public function isActive(bool $onlyDev = true): bool
    {
        return $this === self::IN_PROGRESS
            || $this === self::TECH_REVIEW
            || $this === self::FUNCTIONAL_REVIEW
            || ($onlyDev === false && $this === self::ACCEPTANCE_TESTING);
    }

    public function isWaiting(): bool
    {
        return in_array($this, [
            self::FEEDBACK_TO_PROCESS,
            self::PENDING_TR,
            self::PENDING_FR,
            self::PENDING_AT,
            self::PENDING_RELEASE,
        ]);
    }

    public function isCycle(): bool
    {
        return in_array($this, [
            self::FEEDBACK_TO_PROCESS,
            self::IN_PROGRESS,
            self::PENDING_TR,
            self::TECH_REVIEW,
            self::PENDING_FR,
            self::FUNCTIONAL_REVIEW,
            self::PENDING_AT,
            self::ACCEPTANCE_TESTING,
            self::PENDING_RELEASE,
        ]);
    }
}
