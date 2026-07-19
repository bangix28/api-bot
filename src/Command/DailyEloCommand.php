<?php

namespace App\Command;

use App\Application\MatchHistory\RefreshData\RefreshAllMatchHistoryHandler;
use App\Repository\RiotAccountRepository;
use App\Services\RiotApiServices\RiotApiServices;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'daily-elo',
    description: 'Enregistre l\'elo quotidien et rafraîchit l\'historique des joueurs',
)]
class DailyEloCommand extends Command
{
    public function __construct(
        private readonly RiotApiServices              $riotApiService,
        private readonly RefreshAllMatchHistoryHandler $refreshAllMatchHistory,
        private readonly RiotAccountRepository        $riotAccountRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Historique (orchestrateur mutualisé)
        $this->refreshAllMatchHistory->handle();

        // Snapshot d'elo quotidien (feature propre à cette commande)
        foreach ($this->riotAccountRepository->findAll() as $account) {
            $this->riotApiService->getDailyElo($account);
        }

        $io->success('Commande effectuée avec succès !');

        return Command::SUCCESS;
    }
}
