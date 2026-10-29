<?php

namespace App\Domain\LanChallenge;

/**
 * Thème transversal aux raretés : une carte thématique se tire, rapporte et se
 * joue comme les autres de sa rareté. Le thème ne sert qu'à la présentation.
 */
enum Theme: string
{
    /** Jouer pour ses coéquipiers, à la Yuumi. */
    case BOND = 'bond';

    public function label(): string
    {
        return match ($this) {
            self::BOND => 'Lien',
        };
    }
}
