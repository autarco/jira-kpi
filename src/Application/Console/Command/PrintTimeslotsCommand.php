<?php

namespace Autarco\JiraKpi\Application\Console\Command;

use Marble\EntityManager\EntityManager;
use Autarco\JiraKpi\Domain\Model\Issue\Issue;
use Autarco\JiraKpi\Domain\Model\Unit\Second;
use Autarco\JiraKpi\Domain\Service\TimeslotCalculator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
        $this->addOption('active', 'a');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $key       = $input->getArgument('key');
        $issue     = $this->entityManager->getRepository(Issue::class)->fetchOneBy(['key' => $key]);
        $timeslots = $this->timeslotCalculator->calculateTimeslots($issue);
        $total     = 0;

        $table = new Table($output);
        $table->setHeaders(['Status', 'Duration', 'From', 'To']);

        foreach ($timeslots as $timeslot) {
            if ($timeslot->status->isActive() || !$input->getOption('active')) {
                $duration = $timeslot->getDuration();

                $table->addRow([
                    $timeslot->status->name,
                    $duration ? round($duration->toDay()->value, 1) : '(ongoing)',
                    $timeslot->from->toDateTimeString(),
                    $timeslot->to?->toDateTimeString() ?: '(ongoing)',
                ]);

                if ($timeslot !== $timeslots[0]) {
                    // don't count time on backlog
                    $total += $duration?->value ?? 0;
                }
            }
        }

        $table->addRow(new TableSeparator());
        $table->addRow([
            '',
            round((new Second($total))->toDay()->value, 1),
            '',
            '',
        ]);

        $table->render();

        return Command::SUCCESS;
    }

}
