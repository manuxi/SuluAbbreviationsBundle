<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Command;

use Manuxi\SuluAbbreviationsBundle\Migration\PageReferenceMigrator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'sulu:abbreviations:migrate-page-references',
    description: 'Replaces the integer ids of abbreviations in pages and snippets with the uuids of the migrated abbreviations'
)]
class MigratePageReferencesCommand extends Command
{
    public function __construct(
        private readonly PageReferenceMigrator $migrator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Counts the references which would be rewritten, nothing is changed');
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

        if (!$result->mappingFound) {
            $io->warning('No migrated abbreviations found, run sulu:abbreviations:migrate-to-content first.');

            return Command::SUCCESS;
        }

        $io->table(['', ''], [
            ['Content nodes checked (all workspaces)', $result->nodes],
            ['Selections rewritten', $result->selections],
            ['Links rewritten', $result->links],
            ['References to unknown abbreviations (unchanged)', $result->unmapped],
        ]);

        if ($dryRun) {
            $io->note('Dry run: nothing was changed.');

            return Command::SUCCESS;
        }

        $io->success('Page references migrated. Next steps: bin/console sulu:reference:refresh, bin/console sulu:search:reindex');

        return Command::SUCCESS;
    }
}
