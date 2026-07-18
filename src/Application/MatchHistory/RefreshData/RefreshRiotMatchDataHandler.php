<?php

namespace App\Application\MatchHistory\RefreshData;

use App\Domain\MatchHistory\GameHistoryFactory;
use App\Domain\MatchHistory\MatchHistoryRepositoryInterface;
use App\Domain\MatchHistory\RiotMatchApiClientInterface;

class RefreshRiotMatchDataHandler
{
    public function __construct(
       private RiotMatchApiClientInterface $apiClient,
       private MatchHistoryRepositoryInterface $repository
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
            $matchData = $this->apiClient->getMatch($matchId);
            $gameHistory = GameHistoryFactory::fromMatchInfo($matchData, $refreshMatchHistoryCommand->puuid);
            $this->repository->save($gameHistory);
        }

    }

}