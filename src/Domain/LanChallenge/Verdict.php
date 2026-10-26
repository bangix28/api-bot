<?php

namespace App\Domain\LanChallenge;

enum Verdict: string
{
    case SUCCESS = 'success';
    case FAILURE = 'failure';
    /**
     * La donnée nécessaire manque au relevé (ex. résultat de partie non fourni).
     * Jamais assimilé à un échec : le joueur ne doit pas perdre une carte
     * à cause d'une source incomplète (ADR-0005).
     */
    case UNKNOWN = 'unknown';

    public static function of(bool $succeeded): self
    {
        return $succeeded ? self::SUCCESS : self::FAILURE;
    }
}
