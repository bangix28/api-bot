<?php

namespace App\Command;

use App\Application\MatchHistory\RefreshData\RefreshAllMatchHistoryHandler;
use App\Repository\RiotAccountRepository;
use App\Services\RiotApiServices\RiotApiServices;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
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
        private readonly LoggerInterface              $refreshLogger = new NullLogger(),
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $start = hrtime(true);
        $this->refreshLogger->info('Commande daily-elo démarrée');

        try {
            // Historique (orchestrateur mutualisé)
            $this->refreshAllMatchHistory->handle();

            // Snapshot d'elo quotidien (feature propre à cette commande)
            foreach ($this->riotAccountRepository->findAll() as $account) {
                $this->riotApiService->getDailyElo($account);
            }
        } catch (\Exception $e) {
            $this->refreshLogger->error('Commande daily-elo échouée', [
                'duration_s' => $this->elapsedSeconds($start),
                'exception' => $e,
            ]);
            $io->error(sprintf('Échec du daily-elo : %s', $e->getMessage()));

            // Exit code non nul : indispensable pour que cron/monitoring voie l'échec.
            return Command::FAILURE;
        }

        $this->refreshLogger->info('Commande daily-elo terminée', [
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
