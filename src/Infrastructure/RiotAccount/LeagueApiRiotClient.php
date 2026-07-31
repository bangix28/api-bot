<?php

namespace App\Infrastructure\RiotAccount;

use App\Domain\RiotAccount\MiniSeries;
use App\Domain\RiotAccount\RankedQueueEntity;
use App\Domain\RiotAccount\RankedRank;
use App\Domain\RiotAccount\RankedTier;
use App\Domain\RiotAccount\RiotAccountRefreshData;
use App\Domain\RiotAccount\RiotApiClientInterface;
use App\Infrastructure\Riot\RiotApiGateway;
use RiotAPI\Base\Exceptions\GeneralException;
use RiotAPI\Base\Exceptions\RequestException;
use RiotAPI\Base\Exceptions\ServerException;
use RiotAPI\Base\Exceptions\ServerLimitException;
use RiotAPI\Base\Exceptions\SettingsException;
use RiotAPI\LeagueAPI\Objects\LeagueEntryDto;

class LeagueApiRiotClient implements RiotApiClientInterface
{

    public function __construct(private RiotApiGateway $validationController)
    {
    }

    /**
     * @throws ServerException
     * @throws ServerLimitException
     * @throws SettingsException
     * @throws RequestException
     * @throws GeneralException
     */
    public function getAccount(string $puuid): RiotAccountRefreshData
    {
        $summoner = $this->validationController->getSummonerAcountsDetails($puuid);

        // entrées ranked → une par file (SoloQ, Flex...)
        $entries = $this->validationController->getRankedsInformationsById($puuid);

        $soloEntry = array_find(
            $entries,
            static fn($entry) => $entry->queueType === 'RANKED_SOLO_5x5',
        );

        $flexEntry = array_find(
            $entries,
            static fn($entry) => $entry->queueType === 'RANKED_FLEX_SR',
        );

        // SoloQ : UNRANKED par défaut (le score du classement en dépend).
        // Flex : null si non classé — l'absence est une info, pas un UNRANKED synthétique.
        $rankedSolo = $this->mapEntry($soloEntry)
            ?? new RankedQueueEntity(RankedRank::UNRANKED, RankedTier::UNRANKED, 0, 0, 0);

        return new RiotAccountRefreshData(
            $rankedSolo,
            $this->mapEntry($flexEntry),
            (int) ($summoner->summonerLevel ?? 0),
            (string) ($summoner->profileIconId ?? 0),
        );
    }

    private function mapEntry(?LeagueEntryDto $entry): ?RankedQueueEntity
    {
        if ($entry === null) {
            return null;
        }

        $miniSeries = null;
        if (isset($entry->miniSeries)) {
            $miniSeries = new MiniSeries(
                (int) $entry->miniSeries->wins,
                (int) $entry->miniSeries->losses,
                (int) $entry->miniSeries->target,
                (string) $entry->miniSeries->progress,
            );
        }

        return new RankedQueueEntity(
            RankedRank::fromString($entry->rank),
            RankedTier::fromString($entry->tier),
            (int) $entry->leaguePoints,
            (int) $entry->wins,
            (int) $entry->losses,
            (bool) ($entry->hotStreak ?? false),
            (bool) ($entry->veteran ?? false),
            (bool) ($entry->freshBlood ?? false),
            $miniSeries,
        );
    }
}
