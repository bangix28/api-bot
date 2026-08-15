<?php

namespace App\Domain\RankedRace;

/**
 * Première partie classée d'un joueur dans une fenêtre, datée à la seconde.
 *
 * Complète ce que la série de relevés sait déjà : celle-ci situe le départ à
 * l'instant d'OBSERVATION de la partie, donc à la cadence du cron près. Le
 * match, lui, porte son heure de fin et sa durée.
 */
final readonly class RankedGameStart
{
    public function __construct(
        public string $matchId,
        public \DateTimeImmutable $startedAt,
    ) {
    }
}
