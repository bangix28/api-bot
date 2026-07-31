<?php

namespace App\Infrastructure\RiotAccount;

use App\Application\RiotAccount\RefreshData\AccountViewModel;
use App\Application\RiotAccount\RefreshData\RefreshPresenterInterface;
use App\Domain\RiotAccount\RiotAccountEntity;

class RefreshViewPresenter implements RefreshPresenterInterface
{
    /** @var AccountViewModel[] */
    private array $viewModel = [];

    /** @param RiotAccountEntity[] $accounts */
    public function present(array $accounts): void
    {
        foreach ($accounts as $account) {
            $ranked = $account->getRankedSolo();

            $this->viewModel[] = new AccountViewModel(
                $account->getSummonerName(),
                $ranked->getTier()->value,
                $ranked->getDivision()->value,
                $ranked->getLeaguePoints(),
            );
        }
    }

    /** @return AccountViewModel[] */
    public function viewModel(): array
    {
        return $this->viewModel;
    }
}
