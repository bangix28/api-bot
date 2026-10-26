<?php

namespace App\Tests\Domain\LanChallenge;

use App\Domain\LanChallenge\CardOffer;
use App\Domain\LanChallenge\Challenge;
use PHPUnit\Framework\TestCase;

class CardOfferTest extends TestCase
{
    public function testUneOffreContientTroisCartesDifferentes(): void
    {
        $offer = new CardOffer([Challenge::DOUBLE_KILL, Challenge::PRUDENT, Challenge::PENTA_KILL]);

        $this->assertSame(Challenge::PENTA_KILL, $offer->cardAt(2));
    }

    public function testDeuxFoisLaMemeCarteEstRefuse(): void
    {
        // Une offre « Doublé, Doublé, Prudent » ne laisserait que deux vrais choix.
        $this->expectException(\InvalidArgumentException::class);

        new CardOffer([Challenge::DOUBLE_KILL, Challenge::DOUBLE_KILL, Challenge::PRUDENT]);
    }

    public function testUneOffreDeDeuxCartesEstRefusee(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CardOffer([Challenge::DOUBLE_KILL, Challenge::PRUDENT]);
    }

    public function testRemplacerUneCarteLaisseLesAutresEnPlace(): void
    {
        $offer = (new CardOffer([Challenge::DOUBLE_KILL, Challenge::PRUDENT, Challenge::PENTA_KILL]))
            ->withCardAt(1, Challenge::HUNTER);

        $this->assertSame([Challenge::DOUBLE_KILL, Challenge::HUNTER, Challenge::PENTA_KILL], $offer->cards);
    }
}
