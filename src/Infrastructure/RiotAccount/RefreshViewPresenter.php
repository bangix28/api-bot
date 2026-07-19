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
            $ranked = $account->getRanked();

            $this->viewModel[] = new AccountViewModel(
                $account->getSummonerName(),
                $ranked->getSoloTier()->value,
                $ranked->getSoloDivision()->value,
                $ranked->getSoloLeaguePoints(),
            );
        }
    }

    /** @return AccountViewModel[] */
    public function viewModel(): array
    {
        return $this->viewModel;
    }
}
