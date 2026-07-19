<?php

namespace App\Command;

use App\Application\MatchHistory\RefreshData\RefreshAllMatchHistoryHandler;
use App\Application\RiotAccount\RefreshData\RefreshRiotAccountDataHandler;
use App\Infrastructure\RiotAccount\NullRefreshPresenter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'refreshSummoners',
    description: 'Refresh les ranks et l\'historique des joueurs',
)]
class RefreshSummonersCommand extends Command
{
    public function __construct(
        private readonly RefreshRiotAccountDataHandler $refreshRankedHandler,
        private readonly RefreshAllMatchHistoryHandler $refreshAllMatchHistory,
    ) {
        parent::__construct();
    }

    /**
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Refresh ranked (pas de vue en CLI -> presenter no-op)
        $this->refreshRankedHandler->handle(new NullRefreshPresenter());

        // Refresh de l'historique de tous les comptes
        $this->refreshAllMatchHistory->handle();

        $io->success('Commande effectuée avec succès !');

        return Command::SUCCESS;
    }
}
