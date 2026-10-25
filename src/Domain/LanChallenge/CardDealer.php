<?php

namespace App\Domain\LanChallenge;

/**
 * Tire les cartes : d'abord la rareté selon son poids, puis une carte de
 * cette rareté au hasard — comme les augments, où le prismatique est rare
 * quel que soit le nombre de cartes prismatiques du catalogue.
 */
final readonly class CardDealer
{
    public function __construct(private RandomizerInterface $randomizer)
    {
    }

    public function dealOffer(): CardOffer
    {
        $cards = [];
        while (count($cards) < CardOffer::SIZE) {
            $cards[] = $this->drawExcluding($cards);
        }

        return new CardOffer($cards);
    }

    /**
     * Remplace une carte de l'offre par une carte absente de l'offre :
     * relancer ne doit jamais rendre la carte qu'on vient de refuser.
     */
    public function reroll(CardOffer $offer, int $slot): CardOffer
    {
        return $offer->withCardAt($slot, $this->drawExcluding($offer->cards));
    }

    /**
     * @param Challenge[] $excluded
     */
    private function drawExcluding(array $excluded): Challenge
    {
        $notExcluded = static fn(array $cards) => array_values(array_filter(
            $cards,
            static fn(Challenge $c) => !in_array($c, $excluded, true),
        ));

        $pool = $notExcluded(Challenge::ofRarity($this->drawRarity()));

        // Rareté épuisée par les exclusions : on pioche dans tout le catalogue
        // plutôt que d'échouer.
        if ($pool === []) {
            $pool = $notExcluded(Challenge::cases());
        }

        return $pool[$this->randomizer->pickInt(0, count($pool) - 1)];
    }

    private function drawRarity(): Rarity
    {
        $roll = $this->randomizer->pickInt(1, 100);

        foreach (Rarity::cases() as $rarity) {
            if ($roll <= $rarity->drawWeight()) {
                return $rarity;
            }
            $roll -= $rarity->drawWeight();
        }

        throw new \LogicException('La somme des poids de rareté doit valoir 100.');
    }
}
