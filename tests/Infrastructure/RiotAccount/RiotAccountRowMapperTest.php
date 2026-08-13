<?php

namespace App\Tests\Infrastructure\RiotAccount;

use App\Domain\RiotAccount\RankedRank;
use App\Domain\RiotAccount\RankedTier;
use App\Domain\RiotAccount\RankedTierNotExistException;
use App\Entity\RiotAccount;
use App\Infrastructure\RiotAccount\RiotAccountRowMapper;
use PHPUnit\Framework\TestCase;

class RiotAccountRowMapperTest extends TestCase
{
    public function testMapsRowWithNullRankedColumnsAsUnranked(): void
    {
        // Arrange : la ligne d'un compte ajouté à la main, seule l'identité est remplie.
        // Toutes les colonnes ranked sont NULL — c'est ce cas qui levait
        // « RankedRank::fromString(): Argument #1 must be of type string, null given ».
        $row = (new RiotAccount())
            ->setRiotId('Pseudo#EUW')
            ->setPuuid('puuid-1')
            ->setSummonerName(null)
            ->setSummonerRankedSoloTier(null)
            ->setSummonerRankedSoloRank(null)
            ->setSummonerRankedSoloLeaguePoints(null)
            ->setSummonerRankedSoloLosses(null)
            ->setSummonerRankedSoloWins(null)
            ->setSummonerLevel(null);

        // Act
        $account = RiotAccountRowMapper::map($row);

        // Assert : lu comme « non classé », sans exception
        $this->assertSame('Pseudo#EUW', $account->getRiotID());
        $this->assertSame('puuid-1', $account->getPuuid());
        $this->assertSame('', $account->getSummonerName());
        $this->assertSame(0, $account->getSummonerLevel());
        $this->assertSame('0', $account->getLogoId());
        $this->assertSame(RankedTier::UNRANKED, $account->getRankedSolo()->getTier());
        $this->assertSame(RankedRank::UNRANKED, $account->getRankedSolo()->getDivision());
        $this->assertSame(0, $account->getRankedSolo()->getLeaguePoints());
        $this->assertSame(0, $account->getRankedSolo()->getWins());
        $this->assertSame(0, $account->getRankedSolo()->getLosses());
        $this->assertNull($account->getRankedSolo()->getMiniSeries());
        $this->assertNull($account->getRankedFlex());
    }

    public function testMapsRankedRowWithFlagsAndMiniSeries(): void
    {
        $row = $this->rankedRow()
            ->setSoloHotStreak(true)
            ->setSoloVeteran(false)
            ->setSoloFreshBlood(true)
            ->setSoloMiniSeriesWins(2)
            ->setSoloMiniSeriesLosses(1)
            ->setSoloMiniSeriesTarget(3)
            ->setSoloMiniSeriesProgress('WLW');

        $solo = RiotAccountRowMapper::map($row)->getRankedSolo();

        $this->assertSame(RankedTier::GOLD, $solo->getTier());
        $this->assertSame(RankedRank::II, $solo->getDivision());
        $this->assertSame(50, $solo->getLeaguePoints());
        $this->assertSame(40, $solo->getWins());
        $this->assertSame(20, $solo->getLosses());
        $this->assertTrue($solo->isHotStreak());
        $this->assertFalse($solo->isVeteran());
        $this->assertTrue($solo->isFreshBlood());
        $this->assertSame(2, $solo->getMiniSeries()->wins);
        $this->assertSame(3, $solo->getMiniSeries()->target);
        $this->assertSame('WLW', $solo->getMiniSeries()->progress);
    }

    public function testMapsMiniSeriesOnlyWhenTargetIsSet(): void
    {
        // Des restes de Bo5 sans objectif ne décrivent pas une série en cours.
        $row = $this->rankedRow()
            ->setSoloMiniSeriesWins(2)
            ->setSoloMiniSeriesLosses(1)
            ->setSoloMiniSeriesTarget(null);

        $this->assertNull(RiotAccountRowMapper::map($row)->getRankedSolo()->getMiniSeries());
    }

    public function testMapsFlexWhenTierAndRankAreSet(): void
    {
        $row = $this->rankedRow()
            ->setSummonerRankedFlexTier('SILVER')
            ->setSummonerRankedFlexRank('IV')
            ->setSummonerRankedFlexLeaguePoints(10)
            ->setSummonerRankedFlexWins(12)
            ->setSummonerRankedFlexLosses(8);

        $flex = RiotAccountRowMapper::map($row)->getRankedFlex();

        $this->assertNotNull($flex);
        $this->assertSame(RankedTier::SILVER, $flex->getTier());
        $this->assertSame(RankedRank::IV, $flex->getDivision());
        $this->assertSame(10, $flex->getLeaguePoints());
        $this->assertSame(12, $flex->getWins());
        $this->assertSame(8, $flex->getLosses());
    }

    public function testMapsFlexAsNullWhenBlockIsPartial(): void
    {
        // Bloc flex incomplet : lu comme une absence de classement flex,
        // le prochain refresh réécrit les 5 colonnes d'un coup.
        $withoutRank = $this->rankedRow()
            ->setSummonerRankedFlexTier('SILVER')
            ->setSummonerRankedFlexRank(null);

        $this->assertNull(RiotAccountRowMapper::map($withoutRank)->getRankedFlex());

        $emptyStrings = $this->rankedRow()
            ->setSummonerRankedFlexTier('')
            ->setSummonerRankedFlexRank('');

        $this->assertNull(RiotAccountRowMapper::map($emptyStrings)->getRankedFlex());
    }

    public function testThrowsOnInvalidTierValue(): void
    {
        // Contrat : le mapper lève sur une valeur aberrante ; c'est le repository
        // qui isole la ligne pour ne pas casser la lecture des autres comptes.
        $row = $this->rankedRow()->setSummonerRankedSoloTier('DIAMONDD');

        $this->expectException(RankedTierNotExistException::class);
        RiotAccountRowMapper::map($row);
    }

    private function rankedRow(): RiotAccount
    {
        return (new RiotAccount())
            ->setRiotId('Pseudo#EUW')
            ->setPuuid('puuid-1')
            ->setSummonerName('Pseudo')
            ->setSummonerRankedSoloTier('GOLD')
            ->setSummonerRankedSoloRank('II')
            ->setSummonerRankedSoloLeaguePoints('50')
            ->setSummonerRankedSoloWins(40)
            ->setSummonerRankedSoloLosses('20')
            ->setSummonerLevel(150)
            ->setLogoId('20');
    }
}
