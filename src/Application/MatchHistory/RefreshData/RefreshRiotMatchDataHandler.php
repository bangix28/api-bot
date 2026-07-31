<?php

namespace App\Application\MatchHistory\RefreshData;

use App\Domain\MatchHistory\GameHistoryFactory;
use App\Domain\MatchHistory\MatchHistoryRepositoryInterface;
use App\Domain\MatchHistory\RiotMatchApiClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class RefreshRiotMatchDataHandler
{
    public function __construct(
       private RiotMatchApiClientInterface     $apiClient,
       private MatchHistoryRepositoryInterface $repository,
       private LoggerInterface                 $refreshLogger = new NullLogger(),
    )
    {
    }

    /**
     * @throws \Exception
     */
    public function handle(RefreshMatchHistoryCommand $refreshMatchHistoryCommand): void
    {
        $matchIds = $this->apiClient->getMatchIds($refreshMatchHistoryCommand->puuid, $refreshMatchHistoryCommand->since);

        foreach ($matchIds as $matchId) {
            try {
                // Match déjà en base : pas d'appel Riot, pas d'insertion (idempotence).
                if ($this->repository->exists($matchId, $refreshMatchHistoryCommand->puuid)) {
                    continue;
                }

                $matchData = $this->apiClient->getMatch($matchId);

                if ($matchData === null) {
                    continue;
                }

                $gameHistory = GameHistoryFactory::fromMatchInfo($matchData, $refreshMatchHistoryCommand->puuid);
                $this->repository->save($gameHistory);
            } catch (\Exception $e) {
                // Un match corrompu (joueur absent, compte introuvable...) ne doit pas
                // interrompre le refresh des autres matchs du compte.
                $this->refreshLogger->warning('Refresh du match ignoré', [
                    'matchId' => $matchId,
                    'puuid' => $refreshMatchHistoryCommand->puuid,
                    'exception' => $e,
                ]);
            }
        }

    }

}