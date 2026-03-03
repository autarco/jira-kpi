<?php

namespace Autarco\JiraKpi\Domain\Model\Issue;

use Carbon\CarbonImmutable;
use Marble\Entity\Entity;
use Marble\Entity\Identifier;
use Marble\Entity\SimpleId;
use Autarco\JiraKpi\Domain\Model\Unit\StoryPoint;

class Issue implements Entity
{
    private ?string $causeKey = null;
    private ?WorkCategory $category = null;

    public function __construct(
        private readonly SimpleId $key,
        private IssueType         $type,
        private CarbonImmutable   $created,
        private string            $summary,
        private ?string           $parentKey,
        private ?StoryPoint       $estimate,
        private IssueStatus       $status,
    ) {
    }

    public function getId(): Identifier
    {
        return $this->key;
    }

    public function getKey(): string
    {
        return (string) $this->key;
    }

    public function getType(): IssueType
    {
        return $this->type;
    }

    public function setType(IssueType $type): void
    {
        $this->type = $type;
    }

    public function getCreated(): CarbonImmutable
    {
        return $this->created;
    }

    public function setCreated(CarbonImmutable $created): void
    {
        $this->created = $created;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    public function setSummary(string $summary): void
    {
        $this->summary = $summary;
    }

    public function getParentKey(): ?string
    {
        return $this->parentKey;
    }

    public function setParentKey(?string $parentKey): void
    {
        $this->parentKey = $parentKey;
    }

    public function getEstimate(): ?StoryPoint
    {
        return $this->estimate;
    }

    public function setEstimate(?StoryPoint $estimate): void
    {
        $this->estimate = $estimate;
    }

    public function getStatus(): IssueStatus
    {
        return $this->status;
    }

    public function setStatus(IssueStatus $status): void
    {
        $this->status = $status;
    }

    public function getCauseKey(): ?string
    {
        return $this->causeKey;
    }

    public function setCauseKey(?string $causeKey): void
    {
        $this->causeKey = $causeKey;
    }

    public function getCategory(): ?WorkCategory
    {
        return $this->category;
    }

    public function setCategory(?WorkCategory $category): void
    {
        $this->category = $category;
    }
}
