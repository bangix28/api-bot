<?php

namespace App\Infrastructure\MatchHistory;

use App\Domain\MatchHistory\CombatStats;
use App\Domain\MatchHistory\MatchData;
use App\Domain\MatchHistory\MatchPerformance;
use App\Domain\MatchHistory\ParticipantData;
use App\Domain\MatchHistory\PlayerBuild;
use App\Domain\MatchHistory\RiotMatchApiClientInterface;
use App\Domain\MatchHistory\Runes;
use App\Domain\MatchHistory\ScoreLine;
use App\Infrastructure\Riot\RiotApiGateway;
use RiotAPI\Base\Exceptions\GeneralException;
use RiotAPI\LeagueAPI\Objects\ParticipantDto;
use RiotAPI\LeagueAPI\Objects\PerksDto;
use RiotAPI\Base\Exceptions\RequestException;
use RiotAPI\Base\Exceptions\ServerException;
use RiotAPI\Base\Exceptions\ServerLimitException;
use RiotAPI\Base\Exceptions\SettingsException;

class LeagueApiRiotMatchClient implements RiotMatchApiClientInterface
{
    public function __construct(private RiotApiGateway $riotApiGateway)
    {

    }

    /**
     * @throws ServerException
     * @throws ServerLimitException
     * @throws SettingsException
     * @throws RequestException
     * @throws GeneralException
     */
    public function getMatchIds(string $puuid, ?int $since): array
    {
        return $this->riotApiGateway->getListIdMatchHistoryLol($puuid, $since);
    }

    /**
     * @throws ServerException
     * @throws ServerLimitException
     * @throws SettingsException
     * @throws RequestException
     * @throws GeneralException
     */
    public function getMatch(string $matchId): ?MatchData
    {
       $match = $this->riotApiGateway->getDataMatchById($matchId);

       if ($match === null) {
           return null;
       }

        $participants = array_map(
            fn(ParticipantDto $p) => $this->mapParticipant($p),
            $match->info->participants
        );

        return new MatchData(
            $match->metadata->matchId ?? $matchId,
            $match->info->queueId,
            $match->info->gameEndTimestamp,
            $match->info->gameDuration,
            $participants
        );
    }

    private function mapParticipant(ParticipantDto $p): ParticipantData
    {
        return new ParticipantData(
            $p->puuid,
            $p->win,
            $p->championId,
            $p->championName ?? '',
            $p->teamPosition ?? '',
            new ScoreLine(
                $p->kills,
                $p->deaths,
                $p->assists,
                $p->champLevel,
                $p->goldEarned,
                $p->totalMinionsKilled + $p->neutralMinionsKilled,
                $p->visionScore,
            ),
            new PlayerBuild(
                [$p->item0, $p->item1, $p->item2, $p->item3, $p->item4, $p->item5, $p->item6],
                $p->summoner1Id,
                $p->summoner2Id,
                $this->mapRunes($p->perks ?? null),
            ),
            new CombatStats(
                $p->totalDamageDealtToChampions,
                $p->totalDamageTaken,
                $p->doubleKills,
                $p->tripleKills,
                $p->quadraKills,
                $p->pentaKills,
                $p->firstBloodKill,
                $p->gameEndedInSurrender,
            ),
            MatchPerformance::fromChallenges($p->challenges),
        );
    }

    private function mapRunes(?PerksDto $perks): Runes
    {
        // Certains modes n'ont pas de runes : tout à 0 plutôt qu'une exception.
        if ($perks === null) {
            return new Runes(0, 0, 0, 0, 0, 0);
        }

        $keystoneId = 0;
        $primaryStyleId = 0;
        $subStyleId = 0;

        foreach ($perks->styles as $style) {
            if ($style->description === 'primaryStyle') {
                $primaryStyleId = $style->style;
                $keystoneId = $style->selections[0]->perk ?? 0;
            }

            if ($style->description === 'subStyle') {
                $subStyleId = $style->style;
            }
        }

        return new Runes(
            $keystoneId,
            $primaryStyleId,
            $subStyleId,
            $perks->statPerks->defense ?? 0,
            $perks->statPerks->flex ?? 0,
            $perks->statPerks->offense ?? 0,
        );
    }
}
