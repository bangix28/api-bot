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

        // L'historique d'abord : il lit lastUpdate (date du run précédent) comme
        // curseur "since", avant que le refresh ranked ne l'écrase à maintenant.
        $this->refreshAllMatchHistory->handle();

        // Refresh ranked (pas de vue en CLI -> presenter no-op)
        $this->refreshRankedHandler->handle(new NullRefreshPresenter());

        $io->success('Commande effectuée avec succès !');

        return Command::SUCCESS;
    }
}
