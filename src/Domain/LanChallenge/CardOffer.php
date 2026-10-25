<?php

namespace App\Domain\LanChallenge;

/**
 * Les trois cartes proposées à un joueur avant une partie. Toutes différentes :
 * une offre « Doublé, Doublé, Prudent » ne laisserait que deux vrais choix.
 */
final readonly class CardOffer
{
    public const int SIZE = 3;

    /**
     * @param Challenge[] $cards
     */
    public function __construct(public array $cards)
    {
        if (count($cards) !== self::SIZE || !array_is_list($cards)) {
            throw new \InvalidArgumentException(sprintf('Une offre contient exactement %d cartes.', self::SIZE));
        }

        if (count(array_unique(array_map(static fn(Challenge $c) => $c->value, $cards))) !== self::SIZE) {
            throw new \InvalidArgumentException('Les cartes d\'une offre doivent être toutes différentes.');
        }
    }

    public function cardAt(int $slot): Challenge
    {
        $this->assertSlot($slot);

        return $this->cards[$slot];
    }

    public function withCardAt(int $slot, Challenge $card): self
    {
        $this->assertSlot($slot);
        $cards = $this->cards;
        $cards[$slot] = $card;

        return new self($cards);
    }

    private function assertSlot(int $slot): void
    {
        if ($slot < 0 || $slot >= self::SIZE) {
            throw new InvalidOfferSlotException(sprintf('Emplacement %d invalide : une offre va de 0 à %d.', $slot, self::SIZE - 1));
        }
    }
}
