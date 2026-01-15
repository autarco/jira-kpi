<?php

namespace Marble\JiraKpi\Domain\Model\Issue;

enum IssueType
{
    case STORY;
    case BUG;
    case IMPROVEMENT;
    case TASK;
    case SUBTASK;
    case EPIC;

    public function hasUsefulActiveTime(): bool
    {
        // We're ignoring subtasks, under the assumption that their parent tickets are in an active state
        // for as long as any of their subtasks are on the board.
        return $this === self::STORY
            || $this === self::BUG
            || $this === self::IMPROVEMENT;
    }

    public static function withStoryPoints(): array
    {
        return [
            self::STORY,
            self::BUG,
            self::IMPROVEMENT,
            self::TASK,
            self::SUBTASK, // these shouldn't actually have SP estimates
        ];
    }
}
