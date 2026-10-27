<?php

namespace App\Application\Player\SyncRiotIds;

use App\Domain\Player\RiotIdDirectoryInterface;
use App\Domain\Player\RiotIdLookupFailedException;
use App\Domain\Player\TrackedRiotAccountRepositoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Aligne le Riot ID des comptes suivis sur celui que Riot connaît (« Pseudo#TAG »).
 *
 * C'est sous ce nom que la Live Client Data API désigne les joueurs pendant une
 * partie, et sous ce nom qu'un joueur lie son compte : il doit suivre les
 * changements de pseudo. Un compte en échec garde son Riot ID et n'empêche pas
 * les autres d'être mis à jour (ADR-0002).
 */
final readonly class SyncRiotIdsHandler
{
    public function __construct(
        private TrackedRiotAccountRepositoryInterface $accounts,
        private RiotIdDirectoryInterface $directory,
        private LoggerInterface $refreshLogger = new NullLogger(),
    ) {
    }

    public function handle(): SyncRiotIdsReport
    {
        $renamed = [];
        $unchanged = 0;
        $failed = [];

        foreach ($this->accounts->findAllTracked() as $account) {
            try {
                $currentRiotId = $this->directory->currentRiotIdOf($account->puuid);
            } catch (RiotIdLookupFailedException $exception) {
                $this->refreshLogger->warning('Riot ID non vérifié : Riot n\'a pas répondu', ['riotId' => $account->riotId, 'exception' => $exception]);
                $failed[] = $account->riotId;

                continue;
            }

            if ($currentRiotId === null) {
                $this->refreshLogger->warning('Riot ID non vérifié : puuid inconnu de Riot', ['riotId' => $account->riotId, 'puuid' => $account->puuid]);
                $failed[] = $account->riotId;

                continue;
            }

            if ($currentRiotId->value === $account->riotId) {
                $unchanged++;

                continue;
            }

            // Deux lignes pour le même Riot ID rendraient la liaison et les relevés ambigus.
            $holder = $this->accounts->findByRiotId($currentRiotId);
            if ($holder !== null && $holder->puuid !== $account->puuid) {
                $this->refreshLogger->warning('Riot ID non mis à jour : déjà porté par un autre compte suivi', [
                    'riotId' => $account->riotId,
                    'currentRiotId' => $currentRiotId->value,
                    'holderPuuid' => $holder->puuid,
                ]);
                $failed[] = $account->riotId;

                continue;
            }

            $this->accounts->renameRiotId($account->puuid, $currentRiotId);
            $this->refreshLogger->info('Riot ID mis à jour depuis Riot', ['from' => $account->riotId, 'to' => $currentRiotId->value]);
            $renamed[] = ['from' => $account->riotId, 'to' => $currentRiotId->value];
        }

        return new SyncRiotIdsReport($renamed, $unchanged, $failed);
    }
}
