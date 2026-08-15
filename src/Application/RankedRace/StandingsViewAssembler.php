<?php

namespace App\Application\RankedRace;

use App\Application\RankedRace\ComputeStandings\ProgressionEntryView;
use App\Application\RankedRace\ComputeStandings\RankSnapshotView;
use App\Application\RankedRace\ComputeStandings\WinrateEntryView;
use App\Application\RankedRace\ComputeStandings\WinrateStandingsView;
use App\Domain\EloSnapshot\RankedQueueType;
use App\Domain\RankedRace\FirstRankedGameFinderInterface;
use App\Domain\RankedRace\NullFirstRankedGameFinder;
use App\Domain\RankedRace\PlayerRaceSeries;
use App\Domain\RankedRace\RaceSnapshot;
use App\Domain\RankedRace\RaceStandingsCalculator;
use App\Domain\RankedRace\RaceWindow;
use App\Domain\RankedRace\WinrateStandings;

/**
 * Transforme les snapshots bruts en vues de classement.
 * Partagé entre la course calendaire (/ranked-race) et les événements admin
 * (/ranked-race-events/{id}) : mêmes règles, mêmes shapes JSON.
 */
final readonly class StandingsViewAssembler
{
    public function __construct(
        private RaceStandingsCalculator $calculator = new RaceStandingsCalculator(),
        private FirstRankedGameFinderInterface $firstGames = new NullFirstRankedGameFinder(),
    ) {
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
    public function progression(array $series, RankedQueueType $queue, RaceWindow $window): array
    {
        $entries = [];
        foreach ($this->calculator->progressionStandings($series) as $standing) {
            $playerSeries = $standing->series;
            $player = $playerSeries->player();

            $entries[] = new ProgressionEntryView(
                $player->riotId,
                $player->summonerName,
                $player->logoId,
                $this->mapRank($playerSeries->baseline()),
                $this->mapRank($playerSeries->finish()),
                $playerSeries->rawProgression(),
                $playerSeries->weightedProgression(),
                $standing->rankRaw,
                $standing->rankWeighted,
                $playerSeries->gamesPlayed(),
                $playerSeries->winrate(),
                $playerSeries->offRaceDelta(),
                self::iso($this->startedAt($playerSeries, $queue, $window)),
                self::iso($playerSeries->lastActivityAt()),
            );
        }

        return $entries;
    }

    /** @param PlayerRaceSeries[] $series */
    public function hasAnyGame(array $series): bool
    {
        foreach ($series as $playerSeries) {
            if ($playerSeries->hasStarted()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Départ du joueur : l'heure de lancement réelle de sa première partie si
     * le match a été collecté, sinon l'instant où le cron l'a observée — soit
     * la cadence de collecte près.
     *
     * L'affinage ne peut que préciser une date déjà correcte, jamais en
     * inventer une : un joueur sans segment n'est pas interrogé.
     */
    private function startedAt(
        PlayerRaceSeries $series,
        RankedQueueType $queue,
        RaceWindow $window,
    ): ?\DateTimeImmutable {
        $observed = $series->startedAt();

        if ($observed === null) {
            return null;
        }

        return $this->firstGames->findFirst($series->player()->riotId, $queue, $window)?->startedAt
            ?? $observed;
    }

    /** ISO-8601 avec décalage : le front ne doit jamais deviner le fuseau. */
    private static function iso(?\DateTimeImmutable $at): ?string
    {
        return $at?->format(\DateTimeInterface::ATOM);
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
