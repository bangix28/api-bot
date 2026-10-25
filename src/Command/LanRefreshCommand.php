<?php

namespace App\Command;

use App\Application\MatchHistory\RefreshData\RefreshAllMatchHistoryHandler;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'lan:refresh',
    description: 'Collecte les parties ARAM : Chaos des participants des LAN en cours',
)]
class LanRefreshCommand extends Command
{
    use LockableTrait;

    public function __construct(
        #[Autowire(service: 'app.lan.refresh_match_history')]
        private readonly RefreshAllMatchHistoryHandler $refreshLanMatchHistory,
        private readonly LoggerInterface               $refreshLogger = new NullLogger(),
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Cron toutes les 2 minutes : un run ralenti par le rate limit Riot
        // déborderait sur le suivant et doublerait les appels.
        if (!$this->lock()) {
            $this->refreshLogger->warning('Commande lan:refresh ignorée : un run est déjà en cours');
            $io->warning('Une collecte de LAN est déjà en cours, run ignoré.');

            // SUCCESS : un chevauchement n'est pas une erreur, le cron ne doit pas alerter.
            return Command::SUCCESS;
        }

        try {
            // Hors LAN, aucune cible : aucun appel Riot.
            $this->refreshLanMatchHistory->handle();
        } catch (\Exception $e) {
            $this->refreshLogger->error('Commande lan:refresh échouée', ['exception' => $e]);
            $io->error(sprintf('Échec de la collecte de LAN : %s', $e->getMessage()));

            return Command::FAILURE;
        } finally {
            $this->release();
        }

        return Command::SUCCESS;
    }
}
