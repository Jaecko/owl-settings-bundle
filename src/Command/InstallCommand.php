<?php

namespace OwlConcept\SettingsBundle\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'owl:settings:install',
    description: 'Create the database tables for owl-settings-bundle',
)]
class InstallCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $settingsTable = 'owl_settings',
        private readonly string $preferencesTable = 'owl_user_preferences',
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Execute the SQL (otherwise displays the SQL only)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');

        $schemaManager = $this->connection->createSchemaManager();
        $schema = new Schema();
        $hasChanges = false;

        // --- owl_settings table ---
        if (!$schemaManager->tablesExist([$this->settingsTable])) {
            $table = $schema->createTable($this->settingsTable);
            $table->addColumn('setting_key', 'string', ['length' => 255]);
            $table->addColumn('setting_value', 'text', ['notnull' => false]);
            $table->addColumn('setting_group', 'string', ['length' => 100]);
            $table->addColumn('setting_type', 'string', ['length' => 50, 'default' => 'text']);
            $table->addColumn('created_at', 'datetime');
            $table->addColumn('updated_at', 'datetime');
            $table->setPrimaryKey(['setting_key']);
            $table->addIndex(['setting_group'], 'idx_owl_setting_group');
            $hasChanges = true;
        }

        // --- owl_user_preferences table ---
        if (!$schemaManager->tablesExist([$this->preferencesTable])) {
            $table = $schema->createTable($this->preferencesTable);
            $table->addColumn('user_id', 'string', ['length' => 255]);
            $table->addColumn('pref_key', 'string', ['length' => 255]);
            $table->addColumn('pref_value', 'text', ['notnull' => false]);
            $table->addColumn('created_at', 'datetime');
            $table->addColumn('updated_at', 'datetime');
            $table->setPrimaryKey(['user_id', 'pref_key']);
            $table->addIndex(['user_id'], 'idx_owl_user_pref_user');
            $hasChanges = true;
        }

        if (!$hasChanges) {
            $io->success('Les tables existent déjà. Rien à faire.');

            return Command::SUCCESS;
        }

        // Generate platform-specific SQL
        $platform = $this->connection->getDatabasePlatform();
        $queries = $schema->toSql($platform);

        if (empty($queries)) {
            $io->success('Aucune modification nécessaire.');

            return Command::SUCCESS;
        }

        if (!$force) {
            $io->title('SQL à exécuter');
            foreach ($queries as $sql) {
                $io->text($sql . ';');
            }
            $io->note('Exécutez avec --force pour appliquer les modifications.');

            return Command::SUCCESS;
        }

        foreach ($queries as $sql) {
            $this->connection->executeStatement($sql);
            $io->text('Exécuté : ' . $sql);
        }

        $io->success('Tables créées avec succès.');

        return Command::SUCCESS;
    }
}
