<?php

namespace App\Tests\Application\RiotAccount;

use App\Domain\RiotAccount\RankedRank;
use App\Domain\RiotAccount\RankedTier;
use App\Domain\RiotAccount\RiotAccountEntity;
use App\Domain\RiotAccount\SummonerRankedEntity;
use App\Infrastructure\RiotAccount\RefreshViewPresenter;
use PHPUnit\Framework\TestCase;

class RefreshViewPresenterTest extends TestCase
{
    public function testPresentMapsDomainAccountToViewModel()
    {
        $presenter = new RefreshViewPresenter();

        $presenter->present([
            new RiotAccountEntity(
                'Pseudo#EUW', 'puuid-1', 'Pseudo',
                new SummonerRankedEntity(RankedRank::II, RankedTier::GOLD, 50, 40, 20),
                150, '20'
            ),
        ]);

        $vm = $presenter->viewModel();

        $this->assertCount(1, $vm);
        $this->assertSame('Pseudo', $vm[0]->summonerName);
        $this->assertSame('GOLD', $vm[0]->tier);       // enum ->value
        $this->assertSame('II', $vm[0]->rank);
        $this->assertSame(50, $vm[0]->leaguePoints);
    }
}