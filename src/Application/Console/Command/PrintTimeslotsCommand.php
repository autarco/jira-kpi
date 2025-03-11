<?php

namespace Marble\JiraKpi\Application\Console\Command;

use Marble\EntityManager\EntityManager;
use Marble\JiraKpi\Domain\Model\Issue\Issue;
use Marble\JiraKpi\Domain\Service\TimeslotCalculator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:timeslots')]
class PrintTimeslotsCommand extends Command
{
    public function __construct(
        private readonly EntityManager      $entityManager,
        private readonly TimeslotCalculator $timeslotCalculator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('key', InputArgument::REQUIRED, 'Issue key');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $key       = $input->getArgument('key');
        $issue     = $this->entityManager->getRepository(Issue::class)->fetchOneBy(['key' => $key]);
        $timeslots = $this->timeslotCalculator->calculateTimeslots($issue);

        $table = new Table($output);
        $table->setHeaders(['Status', 'Duration', 'From', 'To']);

        foreach ($timeslots as $timeslot) {
            $duration = $timeslot->getDuration();

            $table->addRow([
                $timeslot->status->name,
                $duration ? round($duration->toDay()->value, 1) : '(ongoing)',
                $timeslot->from->toDateTimeString(),
                $timeslot->to?->toDateTimeString() ?: '(ongoing)',
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }

}
