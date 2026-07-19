<?php

namespace App\Infrastructure\RiotAccount;

use App\Application\RiotAccount\RefreshData\RefreshPresenterInterface;
use App\Domain\RiotAccount\RiotAccountEntity;

/**
 * Presenter no-op (Null Object) : pour les points d'entrée sans vue (CLI),
 * où le use case exige un presenter mais où l'on n'affiche rien.
 */
final class NullRefreshPresenter implements RefreshPresenterInterface
{
    /** @param RiotAccountEntity[] $accounts */
    public function present(array $accounts): void
    {
    }
}
