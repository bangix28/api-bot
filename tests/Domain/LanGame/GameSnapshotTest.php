<?php

namespace App\Tests\Domain\LanGame;

use App\Domain\LanGame\GameSnapshot;
use PHPUnit\Framework\TestCase;

class GameSnapshotTest extends TestCase
{
    public function testLEmpreinteNeDependNiDeLOrdreNiDeLaCasseDesJoueurs(): void
    {
        $snapshot = GameSnapshotBuilder::aGame()->build();
        $reordered = new GameSnapshot('KIWI', 30, 'Alpha#EUW', array_reverse($snapshot->players), []);
        $otherCase = GameSnapshotBuilder::aGame()->replacing('Charlie#EUW', 'CHARLIE#euw')->build();

        $this->assertSame($snapshot->playersFingerprint(), $reordered->playersFingerprint());
        $this->assertSame($snapshot->playersFingerprint(), $otherCase->playersFingerprint());
        $this->assertStringStartsWith('alpha#euw|bravo#euw|charlie#euw|inconnu1#euw', $snapshot->playersFingerprint());
    }

    public function testLeJoueurDuPosteEstRetrouveSansTenirCompteDeLaCasse(): void
    {
        $snapshot = GameSnapshotBuilder::aGame()->seenBy('alpha#euw')->build();

        $this->assertSame('Alpha#EUW', $snapshot->playerByRiotId('ALPHA#EUW')?->riotId);
        $this->assertNull($snapshot->playerByRiotId('Alpha#EUW2'));
    }

    public function testUnJoueurEstRetrouveParSonPseudo(): void
    {
        $snapshot = GameSnapshotBuilder::aGame()->build();

        $this->assertSame('Charlie#EUW', $snapshot->playerByName('Charlie')?->riotId);
        $this->assertNull($snapshot->playerByName('Turret_T1_C_05_A'));
    }

    public function testLeTempsDeJeuNePeutPasEtreNegatif(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        GameSnapshotBuilder::aGame()->at(-0.1)->build();
    }

    public function testLeJoueurDuPosteDoitEtreDansLaPartie(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        GameSnapshotBuilder::aGame()->seenBy('Autre#EUW')->build();
    }
}
