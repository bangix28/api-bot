<?php

namespace App\Application\RiotAccount\RefreshData;

use App\Domain\RiotAccount\RiotAccountRefreshData;
use App\Domain\RiotAccount\RiotAccountRepositoryInterface;
use App\Domain\RiotAccount\RiotApiClientInterface;

class RefreshRiotAccountDataHandler
{
    public function __construct(
        private RiotAccountRepositoryInterface $repositoryService,
        private RiotApiClientInterface $riotApiService
    ) {}

    public function handle(RefreshPresenterInterface $presenter): void
    {
        $listAccounts = $this->repositoryService->getListAccount();

        $refreshedAccounts = [];
        foreach ($listAccounts as $account)
        {
            $refreshData = $this->riotApiService->getAccount($account->getPuuid());

            $updateAccount = $account
                ->withRanked($refreshData->ranked)
                ->withSummonerLevel($refreshData->summonerLevel)
                ->withLogoId($refreshData->logoId);

            $this->repositoryService->save($updateAccount);

            $refreshedAccounts[] = $updateAccount;
        }

        $presenter->present($refreshedAccounts);
    }

}