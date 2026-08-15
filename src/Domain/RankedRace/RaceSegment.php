<?php

namespace App\Domain\RankedRace;

/**
 * Transition entre deux snapshots consécutifs pendant laquelle au moins une
 * partie a été jouée. C'est l'unité de calcul de la course : ce qui n'est pas
 * un segment ne compte pas.
 *
 * Cette règle unique absorbe trois phénomènes qui polluaient le classement :
 * le decay Master+ (des LP partent sans partie), les lectures transitoires de
 * l'API Riot (un rang aberrant entre deux relevés), et le reset de saison
 * (wins/losses repartent de zéro). Aucun n'a de partie jouée, donc aucun ne
 * produit de segment.
 */
final readonly class RaceSegment
{
    private function __construct(
        public RaceSnapshot $from,
        public RaceSnapshot $to,
    ) {
    }

    /**
     * null si aucune partie n'a été jouée entre les deux points — y compris
     * quand le compteur diminue, ce qui ne peut venir que d'un reset de saison.
     */
    public static function between(RaceSnapshot $from, RaceSnapshot $to): ?self
    {
        if ($to->games() <= $from->games()) {
            return null;
        }

        return new self($from, $to);
    }

    /** Toujours strictement positif : sans partie jouée, le segment n'existe pas. */
    public function games(): int
    {
        return $this->to->games() - $this->from->games();
    }

    public function wins(): int
    {
        return $this->to->ranked->getWins() - $this->from->ranked->getWins();
    }

    public function scoreDelta(): int
    {
        return $this->to->raceScore() - $this->from->raceScore();
    }

    /**
     * Delta payé au tarif du tier de DÉPART : une montée Gold -> Platinum dans
     * le segment est comptée au coefficient Gold, sans découpage à la frontière.
     */
    public function weightedDelta(): float
    {
        return $this->scoreDelta() * TierCoefficient::for($this->from->ranked->getTier());
    }
}
