<?php

namespace App\Application\RiotAccount\RefreshData;

use App\Domain\RiotAccount\RiotAccountRepositoryInterface;
use App\Domain\RiotAccount\RiotApiClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class RefreshRiotAccountDataHandler
{
    public function __construct(
        private RiotAccountRepositoryInterface $repositoryService,
        private RiotApiClientInterface         $riotApiService,
        private LoggerInterface                $refreshLogger = new NullLogger(),
    ) {}

    public function handle(RefreshPresenterInterface $presenter): void
    {
        $listAccounts = $this->repositoryService->getListAccount();

        $refreshedAccounts = [];
        $failed = 0;
        foreach ($listAccounts as $account)
        {
            try {
                $refreshData = $this->riotApiService->getAccount($account->getPuuid());

                $updateAccount = $account
                    ->withRankedSolo($refreshData->rankedSolo)
                    ->withRankedFlex($refreshData->rankedFlex)
                    ->withSummonerLevel($refreshData->summonerLevel)
                    ->withLogoId($refreshData->logoId);

                $this->repositoryService->save($updateAccount);

                $refreshedAccounts[] = $updateAccount;
            } catch (\Exception $exception){
                // L'échec d'un compte ne doit pas interrompre le refresh des autres.
                ++$failed;
                $this->refreshLogger->warning('Refresh du compte ignoré', [
                    'puuid' => $account->getPuuid(),
                    'exception' => $exception,
                ]);
            }
        }

        $this->refreshLogger->info('Refresh des comptes terminé', [
            'ok' => count($refreshedAccounts),
            'failed' => $failed,
        ]);

        $presenter->present($refreshedAccounts);
    }

}