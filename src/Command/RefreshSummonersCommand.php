<?php

namespace App\Command;

use App\Services\RiotApiServices\RiotApiServices;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'refreshSummoners',
    description: 'Refresh les ranks des joueur',
)]
class RefreshSummonersCommand extends Command
{

    public function __construct(private readonly RiotApiServices $riotApiService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    /**
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->riotApiService->getListRanked();

        $io->success('Commande effectuée avec succès !');

        return 1;
    }
}
