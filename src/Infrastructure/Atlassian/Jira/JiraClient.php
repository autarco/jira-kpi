<?php

namespace Marble\JiraKpi\Infrastructure\Atlassian\Jira;

use Carbon\CarbonImmutable;
use Marble\Entity\SimpleId;
use Marble\EntityManager\EntityManager;
use Marble\JiraKpi\Domain\Model\Issue\Issue;
use Marble\JiraKpi\Domain\Model\Issue\IssueStatus;
use Marble\JiraKpi\Domain\Model\Issue\IssueTransition;
use Marble\JiraKpi\Domain\Model\Issue\IssueType;
use Marble\JiraKpi\Domain\Model\Issue\WorkCategory;
use Marble\JiraKpi\Domain\Model\Unit\StoryPoint;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use function Symfony\Component\String\u;

class JiraClient
{
    private const int PAGE_SIZE = 100;

    private readonly HttpClientInterface $http;

    public function __construct(
        HttpClientFactory                $httpClientFactory,
        private readonly EntityManager   $entityManager,
        private readonly LoggerInterface $logger,
    ) {
        $this->http = $httpClientFactory->createClient();
    }

    public function importIssues(CarbonImmutable $updatedAfter): void
    {
        $nextPageToken = null;

        do {
            $response = $this->http->request('GET', 'search/jql', [
                'query' => [
                    'jql'           => sprintf('project IN (%s) and updated >= "%s" order by key ASC', $_ENV['PROJECT_KEY'], $updatedAfter->toDateString()),
                    'fields'        => 'summary,customfield_10028,created,issuetype,labels,status,issuelinks,parent',
                    'maxResults'    => self::PAGE_SIZE,
                    'nextPageToken' => $nextPageToken,
                ],
            ]);

            $payload       = $response->toArray();
            $nextPageToken = $payload['nextPageToken'] ?? null;
            $issues        = $payload['issues'];

            if (count($issues) === 0) {
                break;
            }

            $this->logger->notice(sprintf('Importing %d issues (%s - %s)', count($issues), $issues[0]['key'], $issues[array_key_last($issues)]['key']));

            foreach ($issues as $data) {
                $this->logger->info($data['key']);

                $issue = $this->persistIssue($data['key'], $data['fields']);

                $this->importIssueChangelog($issue);
                $this->saveCausingIssue($issue, $data['fields']['issuelinks'] ?? []);
                $this->saveWorkCategory($issue, $data['fields']['labels'] ?? []);
            }

            $this->entityManager->flush();
            $this->entityManager->clear();
        } while (!$payload['isLast']);
    }

    private function persistIssue(string $key, array $fields): Issue
    {
        $type        = IssueType::{u($fields['issuetype']['name'])->upper()->replace('-', '')->toString()};
        $status      = $this->mapJiraStatus($fields['status']['name']);
        $issue       = $this->entityManager->getRepository(Issue::class)->fetchOneBy(['key' => $key]);
        $storyPoints = isset($fields['customfield_10028']) ? new StoryPoint($fields['customfield_10028']) : null;
        $parent      = isset($fields['parent']) ? new SimpleId($fields['parent']['key']) : null;

        if ($issue instanceof Issue) {
            $issue->setType($type);
            $issue->setCreated(CarbonImmutable::make($fields['created']));
            $issue->setSummary($fields['summary']);
            $issue->setParentKey($parent);
            $issue->setEstimate($storyPoints);
            $issue->setStatus($status);
        } else {
            $issue = new Issue(
                key: new SimpleId($key),
                type: $type,
                created: CarbonImmutable::make($fields['created']),
                summary: $fields['summary'],
                parentKey: $parent,
                estimate: $storyPoints,
                status: $status,
            );

            $this->entityManager->persist($issue);
        }

        return $issue;
    }

    private function importIssueChangelog(Issue $issue): void
    {
        $transitions = $this->entityManager->getRepository(IssueTransition::class)->fetchManyBy(['issue' => $issue]);
        $transitions = array_map(fn(IssueTransition $transition): string => $transition->getExternalId(), $transitions);
        $pageStart   = 0;

        do {
            $response = $this->http->request('GET', 'issue/' . $issue->getKey() . '/changelog', [
                'query' => [
                    'maxResults' => self::PAGE_SIZE,
                    'startAt'    => $pageStart,
                ],
            ]);

            $payload   = $response->toArray()['values'];
            $pageStart += count($payload);

            foreach ($payload as $event) {
                if (!in_array($event['id'], $transitions)) {
                    foreach ($event['items'] as $change) {
                        if ($change['field'] === 'status') {
                            $this->persistTransition($issue, $change, $event);
                            break;
                        }
                    }
                }
            }
        } while (count($payload) === self::PAGE_SIZE);
    }

    private function persistTransition(Issue $issue, array $change, array $event): void
    {
        $fromStatus = $this->mapJiraStatus($change['fromString']);
        $toStatus   = $this->mapJiraStatus($change['toString']);

        if ($fromStatus !== $toStatus) {
            $transition = new IssueTransition(
                externalId: new SimpleId($event['id']),
                issue: $issue,
                from: $fromStatus,
                to: $toStatus,
                transitioned: CarbonImmutable::make($event['created']),
            );

            $this->entityManager->persist($transition);
        }
    }

    private function mapJiraStatus(?string $jiraName): IssueStatus
    {
        $jiraName = strtolower($jiraName ?? '');

        if ($_ENV['PROJECT_KEY'] !== 'AUT' && $jiraName === 'pending release') {
            // For mobile dev, due to the release process we'll treat Pending Release as Done.
            $jiraName = '--considered-done';
        }

        return match ($jiraName) {
            default                                         => IssueStatus::TO_DO,
            'selected for development'                      => IssueStatus::SELECTED_FOR_DEV,
            'feedback to process'                           => IssueStatus::FEEDBACK_TO_PROCESS,
            'in progress', 'developing'                     => IssueStatus::IN_PROGRESS,
            'pending tr'                                    => IssueStatus::PENDING_TR,
            'tech review'                                   => IssueStatus::TECH_REVIEW,
            'pending fr'                                    => IssueStatus::PENDING_FR,
            'functional review'                             => IssueStatus::FUNCTIONAL_REVIEW,
            'pending acceptance testing', 'pending uat'     => IssueStatus::PENDING_AT,
            'acceptance testing', 'user acceptance testing' => IssueStatus::ACCEPTANCE_TESTING,
            'pending release'                               => IssueStatus::PENDING_RELEASE,
            'done', 'released', '--considered-done'         => IssueStatus::DONE,
            "won't fix", 'duplicate'                        => IssueStatus::CANCELLED,
        };
    }

    private function saveCausingIssue(Issue $issue, array $links): void
    {
        foreach ($links as $link) {
            if ($link['type']['id'] === '10010' && isset($link['inwardIssue'])) {
                $key = $link['inwardIssue']['key'];

                $issue->setCauseKey($key);

                return; // issue can have only 1 cause
            }
        }
    }

    private function saveWorkCategory(Issue $issue, array $labels): void
    {
        $issue->setCategory(WorkCategory::REQUEST); // default

        if ($issue->getType() === IssueType::BUG) {
            $issue->setCategory(WorkCategory::BUG);
        }

        $projectLabels = ['ProjectTicket'];
        $techLabels    = ['tech-modernization'];

        foreach ($labels as $label) {
            if (in_array($label, $projectLabels)) {
                $issue->setCategory(WorkCategory::ROADMAP);
            } elseif (in_array($label, $techLabels)) {
                $issue->setCategory(WorkCategory::TECH);
            }
        }
    }
}
