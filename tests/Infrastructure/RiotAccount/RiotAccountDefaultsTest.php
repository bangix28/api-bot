<?php

namespace App\Tests\Infrastructure\RiotAccount;

use App\Domain\RiotAccount\RankedRank;
use App\Domain\RiotAccount\RankedTier;
use App\Entity\RiotAccount;
use App\Infrastructure\RiotAccount\RiotAccountDefaults;
use App\Infrastructure\RiotAccount\RiotAccountRowMapper;
use PHPUnit\Framework\TestCase;

class RiotAccountDefaultsTest extends TestCase
{
    public function testApplyUnrankedLeavesNoNullInSoloColumns(): void
    {
        $row = RiotAccountDefaults::applyUnranked(new RiotAccount());

        $this->assertSame(RankedTier::UNRANKED->value, $row->getSummonerRankedSoloTier());
        $this->assertSame(RankedRank::UNRANKED->value, $row->getSummonerRankedSoloRank());
        $this->assertSame('0', $row->getSummonerRankedSoloLeaguePoints());
        $this->assertSame(0, $row->getSummonerRankedSoloWins());
        $this->assertSame('0', $row->getSummonerRankedSoloLosses());
        $this->assertSame(0, $row->getScore());
        $this->assertSame(0, $row->getSummonerLevel());
        $this->assertSame('0', $row->getLogoId());

        // Flex à NULL : l'absence signifie « jamais classé en Flex ».
        $this->assertNull($row->getSummonerRankedFlexTier());
        // Jamais rafraîchi.
        $this->assertNull($row->getLastUpdate());
    }

    public function testUnrankedRowIsReadableByTheDomain(): void
    {
        // L'invariant qui compte : une ligne neuve traverse le mapper sans exception.
        $row = RiotAccountDefaults::applyUnranked(new RiotAccount())
            ->setRiotId('Pseudo#EUW')
            ->setPuuid('puuid-1')
            ->setSummonerName('Pseudo');

        $account = RiotAccountRowMapper::map($row);

        $this->assertSame(RankedTier::UNRANKED, $account->getRankedSolo()->getTier());
        $this->assertSame(0, $account->getRankedSolo()->getScore());
    }

    public function testSummonerNameFromRiotId(): void
    {
        $this->assertSame('Pseudo', RiotAccountDefaults::summonerNameFrom('Pseudo#EUW'));
        $this->assertSame('Pseudo avec espaces', RiotAccountDefaults::summonerNameFrom('Pseudo avec espaces #EUW'));
        $this->assertSame('SansTag', RiotAccountDefaults::summonerNameFrom('SansTag'));
        $this->assertSame('', RiotAccountDefaults::summonerNameFrom('#EUW'));
    }
}
