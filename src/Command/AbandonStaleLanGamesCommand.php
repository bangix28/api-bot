<?php

namespace App\Command;

use App\Application\LanGame\AbandonStaleGames\AbandonStaleGamesHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'lan:abandon-stale-games',
    description: 'Règle en verdict inconnu les parties de LAN sans relevé depuis une heure',
)]
class AbandonStaleLanGamesCommand extends Command
{
    use LockableTrait;

    public function __construct(private readonly AbandonStaleGamesHandler $abandonStaleGames)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console = new SymfonyStyle($input, $output);

        // Deux runs simultanés régleraient deux fois la même partie.
        if (!$this->lock()) {
            $console->warning('Un run est déjà en cours, run ignoré.');

            return Command::SUCCESS;
        }

        $abandoned = $this->abandonStaleGames->handle();
        $console->success(sprintf('%d partie(s) abandonnée(s).', count($abandoned)));

        return Command::SUCCESS;
    }
}
