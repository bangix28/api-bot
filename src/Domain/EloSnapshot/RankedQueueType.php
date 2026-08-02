<?php

namespace App\Domain\EloSnapshot;

enum RankedQueueType: string
{
    case SOLO = 'RANKED_SOLO_5x5';
    case FLEX = 'RANKED_FLEX_SR';

    /**
     * 'solo'|'flex' côté API publique ; null si la valeur est inconnue —
     * c'est à l'appelant de décider quelle exception métier lever.
     */
    public static function tryFromQueryParam(string $value): ?self
    {
        return match ($value) {
            'solo' => self::SOLO,
            'flex' => self::FLEX,
            default => null,
        };
    }

    public function toQueryParam(): string
    {
        return match ($this) {
            self::SOLO => 'solo',
            self::FLEX => 'flex',
        };
    }
}
