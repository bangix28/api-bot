<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Services\RiotApiServices\RiotApiServices;


#[AsCommand(
    name: 'daily-elo',
    description: 'Add a short description for your command',
)]
class DailyEloCommand extends Command
{
    public function __construct(private readonly RiotApiServices $riotApiService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->riotApiService->getListAccount();

        $io->success('Commande effectuée avec succès !');

        return 1;
    }
}
