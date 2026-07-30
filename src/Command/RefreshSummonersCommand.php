<?php

namespace App\Command;

use App\Application\MatchHistory\RefreshData\RefreshAllMatchHistoryHandler;
use App\Application\RiotAccount\RefreshData\RefreshRiotAccountDataHandler;
use App\Infrastructure\RiotAccount\NullRefreshPresenter;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
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
        private readonly LoggerInterface               $refreshLogger = new NullLogger(),
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $start = hrtime(true);
        $this->refreshLogger->info('Commande refreshSummoners démarrée');

        try {
            // L'historique d'abord : il lit lastUpdate (date du run précédent) comme
            // curseur "since", avant que le refresh ranked ne l'écrase à maintenant.
            $this->refreshAllMatchHistory->handle();

            // Refresh ranked (pas de vue en CLI -> presenter no-op)
            $this->refreshRankedHandler->handle(new NullRefreshPresenter());
        } catch (\Exception $e) {
            $this->refreshLogger->error('Commande refreshSummoners échouée', [
                'duration_s' => $this->elapsedSeconds($start),
                'exception' => $e,
            ]);
            $io->error(sprintf('Échec du refresh : %s', $e->getMessage()));

            // Exit code non nul : indispensable pour que cron/monitoring voie l'échec.
            return Command::FAILURE;
        }

        $this->refreshLogger->info('Commande refreshSummoners terminée', [
            'duration_s' => $this->elapsedSeconds($start),
        ]);
        $io->success('Commande effectuée avec succès !');

        return Command::SUCCESS;
    }

    private function elapsedSeconds(int|float $start): float
    {
        return round((hrtime(true) - $start) / 1_000_000_000, 1);
    }
}
