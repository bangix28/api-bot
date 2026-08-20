<?php

namespace App\Domain\RiotAccount;

enum RankedRank: string
{
    case IV = 'IV';
    case III = 'III';
    case II = 'II';
    case I = 'I';
    case UNRANKED = 'UNRANKED';

    public function getScore(): int {
        return match($this) {
            self::IV => 100,
            self::III => 200,
            self::II => 300,
            self::I => 400,
            self::UNRANKED => 0,
        };
    }

    /**
     * Rang de la division dans son palier, de IV = 0 à I = 3.
     *
     * UNRANKED vaut 0 : c'est la division des comptes apex, qui n'en ont pas.
     */
    public function index(): int
    {
        return match($this) {
            self::IV => 0,
            self::III => 1,
            self::II => 2,
            self::I => 3,
            self::UNRANKED => 0,
        };
    }

    public static function fromString(string $value): self
    {
        if ($value === '') {
            return self::UNRANKED;
        }

        return self::tryFrom($value) ?? throw new RankedRankNotExistException("Rank invalide : $value");
    }
}
