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

    /**
     * Rang du palier dans l'échelle, à partir d'Iron = 0. Les trois libellés
     * apex partagent l'indice 7, ce qui fait de leur plancher la suite
     * immédiate de Diamond I.
     */
    public function index(): int
    {
        return match($this) {
            self::IRON => 0,
            self::BRONZE => 1,
            self::SILVER => 2,
            self::GOLD => 3,
            self::PLATINUM => 4,
            self::EMERALD => 5,
            self::DIAMOND => 6,
            self::MASTER, self::GRANDMASTER, self::CHALLENGER => 7,
            self::UNRANKED => throw new \InvalidArgumentException(
                'Pas d\'indice pour UNRANKED : un compte non classé n\'a pas de place dans l\'échelle'
            ),
        };
    }

    /**
     * Master, Grandmaster et Challenger ne sont pas des paliers empilés : ce sont
     * trois libellés posés sur une seule et même échelle de LP, sans divisions et
     * sans plafond. D'où l'absence de contrainte sur les LP.
     */
    public function isApex(): bool
    {
        return match ($this) {
            self::MASTER, self::GRANDMASTER, self::CHALLENGER => true,
            default => false,
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
