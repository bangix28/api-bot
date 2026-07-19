<?php

namespace App\Domain\RiotAccount;

enum RankedTier: string
{
    case IRON = 'IRON';
    case BRONZE = 'BRONZE';
    case SILVER = 'SILVER';
    case GOLD = 'GOLD';
    case PLATINUM = 'PLATINUM';
    case EMERALD = 'EMERALD';
    case DIAMOND = 'DIAMOND';
    case MASTER = 'MASTER';
    case GRANDMASTER = 'GRANDMASTER';
    case CHALLENGER = 'CHALLENGER';
    case UNRANKED = 'UNRANKED';

    public function getScore(): int
    {
        return match($this) {
            self::IRON => 1000,
            self::BRONZE => 2000,
            self::SILVER => 3000,
            self::GOLD => 4000,
            self::PLATINUM => 5000,
            self::EMERALD => 6000,
            self::DIAMOND => 7000,
            self::MASTER => 8000,
            self::GRANDMASTER => 9000,
            self::CHALLENGER => 10000,
            self::UNRANKED => 0,
        };
    }

    public static function fromString(string $value): self
    {
        if ($value === '') {
            return self::UNRANKED;
        }

        return self::tryFrom($value) ?? throw new RankedTierNotExistException("Tier invalide : $value");
    }
}
