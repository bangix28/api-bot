<?php

namespace App\Command;

use App\Services\RiotApiServices\RiotApiServices;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'refreshSummoners',
    description: 'Refresh les ranks des joueur',
)]
class RefreshSummonersCommand extends Command
{
    private RiotApiServices $riotApiService;

    public function __construct(RiotApiServices $riotApiService)
    {
        $this->riotApiService = $riotApiService;
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    /**
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): array
    {
        $io = new SymfonyStyle($input, $output);

        $message = $this->riotApiService->getListRanked();

        $io->success('Commande effectuée avec succès !');

        return $message;
    }
}
