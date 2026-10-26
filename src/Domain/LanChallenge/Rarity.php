<?php

namespace App\Domain\LanChallenge;

/**
 * Rareté d'une carte défi, comme les augments de l'ARAM du chaos :
 * plus une carte est rare, plus elle est difficile et rapporte.
 *
 * Saboteur : la plus rare, et à part. Ses cartes sont secrètes, et visent soit
 * la partie (perdre, mourir le plus), soit un autre joueur (effet immédiat).
 */
enum Rarity: string
{
    case SILVER = 'silver';
    case GOLD = 'gold';
    case PRISMATIC = 'prismatic';
    case SABOTEUR = 'saboteur';

    /** Points d'une carte réussie de cette rareté (hors exception de carte). */
    public function points(): int
    {
        return match ($this) {
            self::SILVER => 10,
            self::GOLD => 25,
            self::SABOTEUR => 40,
            self::PRISMATIC => 60,
        };
    }

    /** Chance, sur 100, qu'un emplacement d'offre tire cette rareté. */
    public function drawWeight(): int
    {
        return match ($this) {
            self::SILVER => 62,
            self::GOLD => 28,
            self::PRISMATIC => 7,
            self::SABOTEUR => 3,
        };
    }
}
