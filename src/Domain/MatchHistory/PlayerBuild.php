<?php

namespace App\Domain\MatchHistory;

readonly class PlayerBuild
{
    public const int ITEM_SLOTS = 7;

    /**
     * @param int[] $items exactement 7 entrées (item0..item6, 0 = slot vide)
     */
    public function __construct(
        public array $items,
        public int $summonerSpell1Id,
        public int $summonerSpell2Id,
        public Runes $runes,
    )
    {
        if (count($this->items) !== self::ITEM_SLOTS) {
            throw new \InvalidArgumentException(
                sprintf('Un build doit contenir exactement %d slots d\'items, %d reçus', self::ITEM_SLOTS, count($this->items))
            );
        }
    }
}
