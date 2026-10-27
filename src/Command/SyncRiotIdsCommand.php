<?php

namespace App\Command;

use App\Application\Player\SyncRiotIds\SyncRiotIdsHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'riot:sync-riot-ids',
    description: 'Aligne le Riot ID des comptes suivis sur celui de Riot (« Pseudo#TAG »)',
)]
class SyncRiotIdsCommand extends Command
{
    public function __construct(private readonly SyncRiotIdsHandler $syncRiotIds)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console = new SymfonyStyle($input, $output);
        $report = $this->syncRiotIds->handle();

        if ($report->renamed !== []) {
            $console->table(['Ancien Riot ID', 'Riot ID actuel'], array_map(
                static fn(array $rename) => [$rename['from'], $rename['to']],
                $report->renamed,
            ));
        }
        if ($report->failed !== []) {
            $console->warning(sprintf('Non vérifiés (détail dans les logs) : %s', implode(', ', $report->failed)));
        }

        $console->success(sprintf(
            '%d mis à jour, %d déjà à jour, %d non vérifiés.',
            count($report->renamed), $report->unchanged, count($report->failed),
        ));

        // Un compte non vérifié garde son Riot ID : ce n'est pas un échec de la commande.
        return Command::SUCCESS;
    }
}
