<?php

namespace App\Tests\Domain\MatchHistory;

use App\Domain\EloSnapshot\RankedQueueType;
use App\Domain\MatchHistory\MatchData;
use App\Domain\MatchHistory\RiotMatchApiClientInterface;

final class FakeRiotMatchApiClient implements RiotMatchApiClientInterface
{
    // Compteur public : permet de vérifier qu'un match déjà connu n'est pas re-téléchargé.
    public int $getMatchCallCount = 0;

    /** Files effectivement interrogées, dans l'ordre. */
    public array $queriedQueues = [];

    /** @param array<string, string[]>|string[] $matchIds par file, ou liste unique pour toutes */
    public function __construct(
        private readonly MatchData $match,
        private readonly array $matchIds = ['match-1'],
        private readonly ?string $failingMatchId = null,
    )
    {
    }

    public function getMatch(string $matchId) :MatchData
    {
        ++$this->getMatchCallCount;

        if ($matchId === $this->failingMatchId) {
            throw new \RuntimeException("Match $matchId illisible");
        }

        // Le match renvoyé porte l'identifiant demandé : sans cela, deux files
        // rendraient deux fois le même match et l'idempotence masquerait le
        // fait qu'on collecte bien des parties distinctes.
        return new MatchData(
            $matchId,
            $this->match->queueId,
            $this->match->gameEndTimeStamp,
            $this->match->gameDuration,
            $this->match->participants,
        );
    }

    public function getMatchIds(string $puuid, RankedQueueType $queue, ?int $since): array
    {
        $this->queriedQueues[] = $queue->value;

        // Liste indexée par file, ou liste unique servie pour chaque file.
        return $this->matchIds[$queue->value] ?? (array_is_list($this->matchIds) ? $this->matchIds : []);
    }
}
