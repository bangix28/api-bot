<?php

namespace App\Application\RankedRace;

use App\Application\RankedRace\ComputeStandings\ProgressionEntryView;
use App\Application\RankedRace\ComputeStandings\RankSnapshotView;
use App\Application\RankedRace\ComputeStandings\WinrateEntryView;
use App\Application\RankedRace\ComputeStandings\WinrateStandingsView;
use App\Domain\RankedRace\PlayerRaceSeries;
use App\Domain\RankedRace\RaceSnapshot;
use App\Domain\RankedRace\RaceStandingsCalculator;
use App\Domain\RankedRace\WinrateStandings;

/**
 * Transforme les snapshots bruts en vues de classement.
 * Partagé entre la course calendaire (/ranked-race) et les événements admin
 * (/ranked-race-events/{id}) : mêmes règles, mêmes shapes JSON.
 */
final readonly class StandingsViewAssembler
{
    public function __construct(private RaceStandingsCalculator $calculator = new RaceStandingsCalculator())
    {
    }

    /**
     * @param RaceSnapshot[] $snapshots
     * @return PlayerRaceSeries[]
     */
    public function groupByPlayer(array $snapshots): array
    {
        $byPlayer = [];
        foreach ($snapshots as $snapshot) {
            $byPlayer[$snapshot->player->riotId][] = $snapshot;
        }

        return array_values(array_map(
            static fn(array $playerSnapshots) => new PlayerRaceSeries($playerSnapshots[0]->player, $playerSnapshots),
            $byPlayer,
        ));
    }

    /**
     * @param PlayerRaceSeries[] $series
     * @return ProgressionEntryView[]
     */
    public function progression(array $series): array
    {
        $entries = [];
        foreach ($this->calculator->progressionStandings($series) as $standing) {
            $playerSeries = $standing->series;
            $player = $playerSeries->player();

            $entries[] = new ProgressionEntryView(
                $player->riotId,
                $player->summonerName,
                $player->logoId,
                $this->mapRank($playerSeries->first()),
                $this->mapRank($playerSeries->last()),
                $playerSeries->rawProgression(),
                $playerSeries->weightedProgression(),
                $standing->rankRaw,
                $standing->rankWeighted,
                $playerSeries->gamesPlayed(),
                $playerSeries->winrate(),
            );
        }

        return $entries;
    }

    /** @param PlayerRaceSeries[] $series */
    public function winrate(array $series, int $minGamesToQualify): WinrateStandingsView
    {
        return $this->mapWinrate(
            $this->calculator->winrateStandings($series, $minGamesToQualify),
            $minGamesToQualify,
        );
    }

    private function mapRank(RaceSnapshot $snapshot): RankSnapshotView
    {
        return new RankSnapshotView(
            $snapshot->ranked->getTier()->value,
            $snapshot->ranked->getDivision()->value,
            $snapshot->ranked->getLeaguePoints(),
            $snapshot->raceScore(),
        );
    }

    private function mapWinrate(WinrateStandings $standings, int $minGamesToQualify): WinrateStandingsView
    {
        $toView = static function (PlayerRaceSeries $playerSeries): WinrateEntryView {
            $player = $playerSeries->player();

            return new WinrateEntryView(
                $player->riotId,
                $player->summonerName,
                $player->logoId,
                $playerSeries->winsDelta(),
                $playerSeries->gamesPlayed() - $playerSeries->winsDelta(),
                $playerSeries->gamesPlayed(),
                $playerSeries->winrate(),
            );
        };

        return new WinrateStandingsView(
            $minGamesToQualify,
            array_map($toView, $standings->qualified),
            array_map($toView, $standings->notQualified),
        );
    }
}
