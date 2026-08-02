<?php

namespace App\Domain\RankedRace;

/**
 * Série ordonnée des snapshots d'un joueur dans la fenêtre de course.
 *
 * Les trous (jours sans snapshot : compte non classé, panne du cron) sont
 * naturels : les deltas se calculent entre snapshots consécutifs CONNUS.
 * Un joueur entré en cours de période a simplement une série plus courte.
 */
final class PlayerRaceSeries
{
    /** @var RaceSnapshot[] */
    private array $snapshots;

    /** @param RaceSnapshot[] $snapshots */
    public function __construct(
        private readonly RacePlayer $player,
        array $snapshots,
    ) {
        if ($snapshots === []) {
            throw new \InvalidArgumentException('Une série de course ne peut pas être vide');
        }

        usort($snapshots, static fn(RaceSnapshot $a, RaceSnapshot $b) => $a->day <=> $b->day);
        $this->snapshots = $snapshots;
    }

    public function player(): RacePlayer
    {
        return $this->player;
    }

    public function first(): RaceSnapshot
    {
        return $this->snapshots[0];
    }

    public function last(): RaceSnapshot
    {
        return $this->snapshots[count($this->snapshots) - 1];
    }

    /** Progression brute : delta de score entre le premier et le dernier snapshot. */
    public function rawProgression(): int
    {
        return $this->last()->raceScore() - $this->first()->raceScore();
    }

    /**
     * Progression pondérée : somme des deltas quotidiens, chacun multiplié par
     * le coefficient du tier de DÉPART du delta. Une montée Gold→Platinum dans
     * la journée est ainsi payée au tarif Gold, sans découpage complexe.
     */
    public function weightedProgression(): float
    {
        $total = 0.0;

        for ($i = 1, $count = count($this->snapshots); $i < $count; $i++) {
            $delta = $this->snapshots[$i]->raceScore() - $this->snapshots[$i - 1]->raceScore();
            $total += $delta * TierCoefficient::for($this->snapshots[$i - 1]->ranked->getTier());
        }

        return round($total, 1);
    }

    /**
     * Parties jouées pendant la période (delta wins+losses).
     * Clamp à 0 : au reset de saison Riot, wins/losses repartent de zéro et le
     * delta brut deviendrait négatif.
     */
    public function gamesPlayed(): int
    {
        return max(0, $this->last()->games() - $this->first()->games());
    }

    public function winsDelta(): int
    {
        return max(0, $this->last()->ranked->getWins() - $this->first()->ranked->getWins());
    }

    /** Winrate de période en %, null si aucune partie jouée. */
    public function winrate(): ?float
    {
        $games = $this->gamesPlayed();

        if ($games === 0) {
            return null;
        }

        return round($this->winsDelta() / $games * 100, 1);
    }

    public function isQualified(int $minGamesToQualify): bool
    {
        return $this->gamesPlayed() >= $minGamesToQualify;
    }
}
