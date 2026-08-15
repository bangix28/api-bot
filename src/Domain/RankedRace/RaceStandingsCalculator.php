<?php

namespace App\Domain\RankedRace;

final class RaceStandingsCalculator
{
    /**
     * Classement Progression : liste triée par progression pondérée, chaque
     * ligne portant aussi son rang en progression brute.
     * Égalité (brute comme pondérée) : moins de parties jouées d'abord
     * (progression plus efficace), puis meilleur winrate, puis riotId.
     *
     * Le riotId final n'a aucun sens métier : il rend seulement l'ordre
     * déterministe. Sans lui, deux joueurs strictement à égalité — cas courant
     * en début de période, où tout le monde est à zéro partie — sortaient dans
     * l'ordre du stockage, donc au gré de la collation de la base.
     *
     * @param PlayerRaceSeries[] $series
     * @return ProgressionStanding[]
     */
    public function progressionStandings(array $series): array
    {
        $byRaw = $series;
        usort($byRaw, static fn(PlayerRaceSeries $a, PlayerRaceSeries $b) =>
            [$b->rawProgression(), $a->gamesPlayed(), $b->winrate() ?? -1.0, $a->player()->riotId]
            <=> [$a->rawProgression(), $b->gamesPlayed(), $a->winrate() ?? -1.0, $b->player()->riotId]);

        $rawRanks = new \SplObjectStorage();
        foreach ($byRaw as $index => $playerSeries) {
            $rawRanks[$playerSeries] = $index + 1;
        }

        $byWeighted = $series;
        usort($byWeighted, static fn(PlayerRaceSeries $a, PlayerRaceSeries $b) =>
            [$b->weightedProgression(), $a->gamesPlayed(), $b->winrate() ?? -1.0, $a->player()->riotId]
            <=> [$a->weightedProgression(), $b->gamesPlayed(), $a->winrate() ?? -1.0, $b->player()->riotId]);

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
            [$b->winrate() ?? -1.0, $b->winsDelta(), $b->gamesPlayed(), $a->player()->riotId]
            <=> [$a->winrate() ?? -1.0, $a->winsDelta(), $a->gamesPlayed(), $b->player()->riotId]);

        usort($notQualified, static fn(PlayerRaceSeries $a, PlayerRaceSeries $b) =>
            [$b->gamesPlayed(), $a->player()->riotId] <=> [$a->gamesPlayed(), $b->player()->riotId]);

        return new WinrateStandings($qualified, $notQualified);
    }
}
