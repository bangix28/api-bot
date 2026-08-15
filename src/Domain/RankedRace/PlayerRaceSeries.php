<?php

namespace App\Domain\RankedRace;

/**
 * Série ordonnée des snapshots d'un joueur, dont on tire ses segments.
 *
 * Le point de départ du joueur n'est pas le premier snapshot de la fenêtre mais
 * la baseline : le snapshot qui précède sa première partie. Elle n'est ni
 * stockée ni déclenchée, elle découle du fait qu'une transition sans partie ne
 * produit aucun segment. C'est ce qui fait démarrer la course « à la première
 * game » sans qu'aucune date de départ n'ait à être persistée.
 *
 * Les trous (compte non classé, panne du cron) restent naturels : les segments
 * se calculent entre snapshots consécutifs CONNUS.
 */
final class PlayerRaceSeries
{
    /** @var RaceSnapshot[] triés par capturedAt croissant */
    private array $snapshots;

    /** @var RaceSegment[]|null mémoïsé : recalculé une seule fois par série */
    private ?array $segments = null;

    /** @param RaceSnapshot[] $snapshots */
    public function __construct(
        private readonly RacePlayer $player,
        array $snapshots,
    ) {
        if ($snapshots === []) {
            throw new \InvalidArgumentException('Une série de course ne peut pas être vide');
        }

        usort($snapshots, static fn(RaceSnapshot $a, RaceSnapshot $b) => $a->capturedAt <=> $b->capturedAt);
        $this->snapshots = $snapshots;
    }

    public function player(): RacePlayer
    {
        return $this->player;
    }

    /** @return RaceSegment[] */
    private function segments(): array
    {
        if ($this->segments !== null) {
            return $this->segments;
        }

        $segments = [];
        for ($i = 1, $count = count($this->snapshots); $i < $count; $i++) {
            $segment = RaceSegment::between($this->snapshots[$i - 1], $this->snapshots[$i]);

            if ($segment !== null) {
                $segments[] = $segment;
            }
        }

        return $this->segments = $segments;
    }

    /** Le joueur a-t-il joué au moins une partie dans la fenêtre ? */
    public function hasStarted(): bool
    {
        return $this->segments() !== [];
    }

    /** Instant où la course a effectivement démarré pour ce joueur. */
    public function startedAt(): ?\DateTimeImmutable
    {
        $segments = $this->segments();

        return $segments === [] ? null : $segments[0]->to->capturedAt;
    }

    /**
     * Rang de départ : le snapshot qui précède la première partie.
     * À défaut de partie jouée, le dernier rang connu — le joueur est alors
     * affiché à sa place actuelle, avec une progression nulle.
     */
    public function baseline(): RaceSnapshot
    {
        $segments = $this->segments();

        return $segments === [] ? $this->lastKnown() : $segments[0]->from;
    }

    /** Rang à l'issue de la dernière partie jouée. */
    public function finish(): RaceSnapshot
    {
        $segments = $this->segments();

        return $segments === [] ? $this->lastKnown() : $segments[count($segments) - 1]->to;
    }

    /** Progression brute : somme des deltas des segments, decay exclu. */
    public function rawProgression(): int
    {
        return array_sum(array_map(static fn(RaceSegment $s) => $s->scoreDelta(), $this->segments()));
    }

    /** Progression pondérée : chaque delta au coefficient de son tier de départ. */
    public function weightedProgression(): float
    {
        return round(
            array_sum(array_map(static fn(RaceSegment $s) => $s->weightedDelta(), $this->segments())),
            1,
        );
    }

    /**
     * LP perdus ou gagnés HORS jeu entre la baseline et la fin : decay,
     * corrections de MMR, ajustements Riot. Exposé à part parce qu'il explique
     * l'écart entre « rang de départ + progression » et le rang réel — ces deux
     * chiffres ne s'additionnent pas, et c'est voulu.
     */
    public function offRaceDelta(): int
    {
        return $this->finish()->raceScore() - $this->baseline()->raceScore() - $this->rawProgression();
    }

    /** Parties jouées pendant la période. */
    public function gamesPlayed(): int
    {
        return array_sum(array_map(static fn(RaceSegment $s) => $s->games(), $this->segments()));
    }

    public function winsDelta(): int
    {
        return array_sum(array_map(static fn(RaceSegment $s) => $s->wins(), $this->segments()));
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

    private function lastKnown(): RaceSnapshot
    {
        return $this->snapshots[count($this->snapshots) - 1];
    }
}
