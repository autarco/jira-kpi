<?php

namespace Marble\JiraKpi\Domain\Model\Issue;

enum IssueType
{
    case STORY;
    case BUG;
    case IMPROVEMENT;
    case UPKEEP;
    case TASK;
    case SUBTASK;
    case EPIC;

    public function hasUsefulActiveTime(): bool
    {
        return $this === self::STORY
            || $this === self::BUG
            || $this === self::IMPROVEMENT
            || $this === self::UPKEEP
            || $this === self::SUBTASK;
    }
}
