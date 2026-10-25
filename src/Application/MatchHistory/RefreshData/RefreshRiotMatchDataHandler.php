<?php

namespace App\Application\MatchHistory\RefreshData;

use App\Domain\EloSnapshot\RankedQueueType;
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
        // Une passe par file classée : Riot n'expose pas les deux en un appel.
        // La boucle est ici et non dans l'adaptateur, pour que « la course suit
        // les deux files » reste une décision lisible côté application.
        foreach (RankedQueueType::cases() as $queue) {
            $this->refreshQueue($refreshMatchHistoryCommand, $queue);
        }
    }

    private function refreshQueue(RefreshMatchHistoryCommand $command, RankedQueueType $queue): void
    {
        $matchIds = $this->apiClient->getMatchIds($command->puuid, $queue, $command->since);

        foreach ($matchIds as $matchId) {
            try {
                // Match déjà en base : pas d'appel Riot, pas d'insertion (idempotence).
                if ($this->repository->exists($matchId, $command->puuid)) {
                    continue;
                }

                $matchData = $this->apiClient->getMatch($matchId);

                if ($matchData === null) {
                    continue;
                }

                $gameHistory = GameHistoryFactory::fromMatchInfo($matchData, $command->puuid);
                $this->repository->save($gameHistory);
            } catch (\Exception $e) {
                // Un match corrompu (joueur absent, compte introuvable...) ne doit pas
                // interrompre le refresh des autres matchs du compte.
                $this->refreshLogger->warning('Refresh du match ignoré', [
                    'matchId' => $matchId,
                    'queue' => $queue->value,
                    'puuid' => $command->puuid,
                    'exception' => $e,
                ]);
            }
        }
    }
}
