<?php

namespace App\Domain\RankedRace;

final class RaceStandingsCalculator
{
    /**
     * Classement Progression : liste triée par progression pondérée, chaque
     * ligne portant aussi son rang en progression brute.
     * Égalité (brute comme pondérée) : moins de parties jouées d'abord
     * (progression plus efficace), puis meilleur winrate.
     *
     * @param PlayerRaceSeries[] $series
     * @return ProgressionStanding[]
     */
    public function progressionStandings(array $series): array
    {
        $byRaw = $series;
        usort($byRaw, static fn(PlayerRaceSeries $a, PlayerRaceSeries $b) =>
            [$b->rawProgression(), $a->gamesPlayed(), $b->winrate() ?? -1.0]
            <=> [$a->rawProgression(), $b->gamesPlayed(), $a->winrate() ?? -1.0]);

        $rawRanks = new \SplObjectStorage();
        foreach ($byRaw as $index => $playerSeries) {
            $rawRanks[$playerSeries] = $index + 1;
        }

        $byWeighted = $series;
        usort($byWeighted, static fn(PlayerRaceSeries $a, PlayerRaceSeries $b) =>
            [$b->weightedProgression(), $a->gamesPlayed(), $b->winrate() ?? -1.0]
            <=> [$a->weightedProgression(), $b->gamesPlayed(), $a->winrate() ?? -1.0]);

        $standings = [];
        foreach ($byWeighted as $index => $playerSeries) {
            $standings[] = new ProgressionStanding($playerSeries, $rawRanks[$playerSeries], $index + 1);
        }

        return $standings;
    }

    /**
     * Classement Winrate : qualifiés (>= seuil de parties) triés winrate desc,
     * départagés par nombre de victoires puis nombre de parties ; non-qualifiés
     * à part, triés par parties jouées (pour le compteur « 3/5 parties »).
     * Le seuil est un simple entier : période calendaire (5/15) ou événement admin.
     *
     * @param PlayerRaceSeries[] $series
     */
    public function winrateStandings(array $series, int $minGamesToQualify): WinrateStandings
    {
        $qualified = array_values(array_filter($series, static fn(PlayerRaceSeries $s) => $s->isQualified($minGamesToQualify)));
        $notQualified = array_values(array_filter($series, static fn(PlayerRaceSeries $s) => !$s->isQualified($minGamesToQualify)));

        usort($qualified, static fn(PlayerRaceSeries $a, PlayerRaceSeries $b) =>
            [$b->winrate() ?? -1.0, $b->winsDelta(), $b->gamesPlayed()]
            <=> [$a->winrate() ?? -1.0, $a->winsDelta(), $a->gamesPlayed()]);

        usort($notQualified, static fn(PlayerRaceSeries $a, PlayerRaceSeries $b) =>
            $b->gamesPlayed() <=> $a->gamesPlayed());

        return new WinrateStandings($qualified, $notQualified);
    }
}
