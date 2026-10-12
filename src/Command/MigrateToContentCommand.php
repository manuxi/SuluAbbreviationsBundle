<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Command;

use Manuxi\SuluAbbreviationsBundle\Migration\LegacyDataMigrator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'sulu:abbreviations:migrate-to-content',
    description: 'Migrates the abbreviations of version 1.x into the content bundle structure of version 2.0'
)]
class MigrateToContentCommand extends Command
{
    public function __construct(
        private readonly LegacyDataMigrator $migrator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Runs the migration in a transaction which is rolled back, nothing is changed');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        try {
            $result = $this->migrator->migrate($dryRun);
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        if (!$result->legacyTablesFound) {
            $io->warning('The legacy tables of version 1.x were not found, nothing to migrate.');

            return Command::SUCCESS;
        }

        $io->table(['', ''], [
            ['Migrated abbreviations', $result->migrated],
            ['Already migrated (skipped)', $result->skipped],
            ['Migrated translations', $result->translations],
            ['Published translations', $result->published],
            ['Routes assigned to the new id', $result->routesUpdated],
            ['Routes created', $result->routesCreated],
            ['Routes of unpublished translations removed', $result->routesRemoved],
        ]);

        if ($dryRun) {
            $io->note('Dry run: nothing was changed.');

            return Command::SUCCESS;
        }

        $io->success('Migration finished. Next steps: bin/console sulu:abbreviations:migrate-page-references, bin/console sulu:reference:refresh abbreviations, bin/console sulu:search:reindex');

        return Command::SUCCESS;
    }
}
