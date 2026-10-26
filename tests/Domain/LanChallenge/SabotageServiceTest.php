<?php

namespace App\Tests\Domain\LanChallenge;

use App\Domain\LanChallenge\CardDealer;
use App\Domain\LanChallenge\CardOffer;
use App\Domain\LanChallenge\Challenge;
use App\Domain\LanChallenge\InvalidSabotageTargetException;
use App\Domain\LanChallenge\PlayerRun;
use App\Domain\LanChallenge\SabotageService;
use App\Domain\LanChallenge\SabotageTargetNotAllowedException;
use App\Domain\LanChallenge\TargetHasNoRerollException;
use PHPUnit\Framework\TestCase;

class SabotageServiceTest extends TestCase
{
    private const int LAN = 7;

    private static function saboteurHolding(Challenge $card, int $rerollsLeft = 1): PlayerRun
    {
        return PlayerRun::restore(self::LAN, 'puuid-saboteur', $rerollsLeft, 0, new CardOffer([$card, Challenge::HUNTER, Challenge::WINGMAN]), null, null, []);
    }

    private static function target(int $rerollsLeft = 2, int $lanId = self::LAN): PlayerRun
    {
        return PlayerRun::restore($lanId, 'puuid-cible', $rerollsLeft, 0, new CardOffer([Challenge::DOUBLE_KILL, Challenge::PRUDENT, Challenge::HUNTER]), null, null, []);
    }

    private static function service(): SabotageService
    {
        return new SabotageService(new CardDealer(new ScriptedRandomizer()));
    }

    public function testPickpocketVoleUneRelanceALaCible(): void
    {
        $outcome = self::service()->sabotage(self::saboteurHolding(Challenge::PICKPOCKET, rerollsLeft: 1), 0, self::target(rerollsLeft: 2));

        $this->assertSame(1, $outcome->target->rerollsLeft);
        $this->assertSame(2, $outcome->saboteur->rerollsLeft);
        $this->assertSame(Challenge::PICKPOCKET, $outcome->sabotage->card);
        $this->assertSame('puuid-cible', $outcome->sabotage->targetPuuid);
    }

    public function testOnNeVolePasUneCibleSansRelance(): void
    {
        $this->expectException(TargetHasNoRerollException::class);

        self::service()->sabotage(self::saboteurHolding(Challenge::PICKPOCKET), 0, self::target(rerollsLeft: 0));
    }

    public function testLaMaledictionMauditLaCibleSansToucherASesRelances(): void
    {
        $outcome = self::service()->sabotage(self::saboteurHolding(Challenge::CURSE, rerollsLeft: 1), 0, self::target(rerollsLeft: 2));

        $this->assertTrue($outcome->target->cursed);
        $this->assertSame(2, $outcome->target->rerollsLeft);
        $this->assertSame(1, $outcome->saboteur->rerollsLeft);
    }

    public function testLeSaboteurRecoitAussitotUneOffreNeuvePourSonMatch(): void
    {
        // Saboter est un bonus en plus de la manche, pas à sa place.
        $outcome = self::service()->sabotage(self::saboteurHolding(Challenge::CURSE), 0, self::target());

        $this->assertSame([Challenge::DOUBLE_KILL, Challenge::PRUDENT, Challenge::TEAM_SPIRIT], $outcome->saboteur->offer->cards);
        $this->assertNull($outcome->saboteur->activeCard);
        $this->assertSame(0, $outcome->saboteur->score);
    }

    public function testOnNeSeSabotePasSoiMeme(): void
    {
        $saboteur = self::saboteurHolding(Challenge::CURSE);

        $this->expectException(InvalidSabotageTargetException::class);

        self::service()->sabotage($saboteur, 0, $saboteur);
    }

    public function testOnNeSabotePasUnJoueurDUneAutreLan(): void
    {
        $this->expectException(InvalidSabotageTargetException::class);

        self::service()->sabotage(self::saboteurHolding(Challenge::CURSE), 0, self::target(lanId: 8));
    }

    public function testUneCarteQuiNeVisePersonneNeSabotePas(): void
    {
        $this->expectException(SabotageTargetNotAllowedException::class);

        self::service()->sabotage(self::saboteurHolding(Challenge::BAD_LOSER), 0, self::target());
    }
}
