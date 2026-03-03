<?php

namespace Autarco\JiraKpi\Application\Console\Command;

use Carbon\CarbonImmutable;
use Marble\EntityManager\EntityManager;
use Autarco\JiraKpi\Infrastructure\Atlassian\Jira\JiraClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:sync')]
class SyncJiraCommand extends Command
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly JiraClient    $jira,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('from', InputArgument::OPTIONAL, 'Sync changes made since this date',
            CarbonImmutable::now()->startOfMonth()->subMonth()->toDateString());
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $since = CarbonImmutable::make($input->getArgument('from'));

        $this->jira->importIssues($since);
        $this->entityManager->flush();

        return Command::SUCCESS;
    }
}
