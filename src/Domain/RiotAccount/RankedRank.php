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

    public static function fromString(string $value): self
    {
        if ($value === '') {
            return self::UNRANKED;
        }

        return self::tryFrom($value) ?? throw new RankedRankNotExistException("Rank invalide : $value");
    }
}
