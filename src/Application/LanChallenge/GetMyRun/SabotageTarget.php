<?php

namespace App\Application\LanChallenge\GetMyRun;

/**
 * Un joueur que le saboteur peut viser. Ne dit rien d'une Malédiction en cours :
 * cette information doit rester cachée à tous.
 */
final readonly class SabotageTarget
{
    public function __construct(
        public string $riotId,
        /** Il lui reste au moins une relance à voler. */
        public bool $canBePickpocketed,
    ) {
    }
}
