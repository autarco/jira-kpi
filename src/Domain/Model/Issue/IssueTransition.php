<?php

namespace Autarco\JiraKpi\Domain\Model\Issue;

use Carbon\CarbonImmutable;
use Marble\Entity\Entity;
use Marble\Entity\Identifier;
use Marble\Entity\SimpleId;

class IssueTransition implements Entity
{
    public function __construct(
        private readonly SimpleId $externalId,
        private readonly Issue    $issue,
        private IssueStatus       $from,
        private IssueStatus       $to,
        private CarbonImmutable   $transitioned,
    ) {
    }

    public function getId(): ?Identifier
    {
        return $this->externalId;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function getIssue(): Issue
    {
        return $this->issue;
    }

    public function getTo(): IssueStatus
    {
        return $this->to;
    }

    public function setTo(IssueStatus $to): void
    {
        $this->to = $to;
    }

    public function getFrom(): IssueStatus
    {
        return $this->from;
    }

    public function setFrom(IssueStatus $from): void
    {
        $this->from = $from;
    }

    public function getTransitioned(): CarbonImmutable
    {
        return $this->transitioned;
    }

    public function setTransitioned(CarbonImmutable $transitioned): void
    {
        $this->transitioned = $transitioned;
    }
}
