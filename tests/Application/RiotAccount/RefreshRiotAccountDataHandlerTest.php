<?php

namespace App\Tests\Application\RiotAccount;

use App\Application\RiotAccount\RefreshData\RefreshRiotAccountDataHandler;
use App\Domain\RiotAccount\RankedRank;
use App\Domain\RiotAccount\RankedTier;
use App\Domain\RiotAccount\RiotAccountEntity;
use App\Domain\RiotAccount\RiotAccountRefreshData;
use App\Domain\RiotAccount\SummonerRankedEntity;
use App\Infrastructure\RiotAccount\RefreshViewPresenter;
use App\Tests\Domain\RiotAccount\FakeRiotApiClient;
use App\Tests\Domain\RiotAccount\InMemoryRiotAccountRepository;
use PHPUnit\Framework\TestCase;

class RefreshRiotAccountDataHandlerTest extends TestCase
{
    public function testHandleUpdatesAccountWithFreshApiData(): void
    {
        // Arrange : un compte "original" non classé, level 30
        $original = new RiotAccountEntity(
            'Pseudo#EUW',
            'puuid-1',
            'Pseudo',
            new SummonerRankedEntity(RankedRank::UNRANKED, RankedTier::UNRANKED, 0, 0),
            30,
            '10'
        );
        $repository = new InMemoryRiotAccountRepository([$original]);

        // ... et des données "fraîches" renvoyées par l'API : GOLD II, 50 LP, level 150
        $refreshData = new RiotAccountRefreshData(
            new SummonerRankedEntity(RankedRank::II, RankedTier::GOLD, 50, 40),
            150,
            '20'
        );
        $apiClient = new FakeRiotApiClient($refreshData);

        $handler = new RefreshRiotAccountDataHandler($repository, $apiClient);

        // Act
        $handler->handle(new RefreshViewPresenter());

        // Assert : les données ranked/level/logo ont été rafraîchies
        $updated = $repository->getListAccount()[0];
        $this->assertSame(RankedTier::GOLD, $updated->getRanked()->getSoloTier());
        $this->assertSame(RankedRank::II, $updated->getRanked()->getSoloDivision());
        $this->assertSame(50, $updated->getRanked()->getSoloLeaguePoints());
        $this->assertSame(150, $updated->getSummonerLevel());
        $this->assertSame('20', $updated->getLogoId());

        // ... et l'identité est préservée (vient de l'entité d'origine, pas du DTO)
        $this->assertSame('Pseudo#EUW', $updated->getRiotID());
        $this->assertSame('puuid-1', $updated->getPuuid());
        $this->assertSame('Pseudo', $updated->getSummonerName());
    }

    public function testHandleUpdatesAccountSendToPresenter()
    {
        // Arrange : un compte "original" non classé, level 30
        $original = new RiotAccountEntity(
            'Pseudo#EUW',
            'puuid-1',
            'Pseudo',
            new SummonerRankedEntity(RankedRank::UNRANKED, RankedTier::UNRANKED, 0, 0),
            30,
            '10'
        );
        $repository = new InMemoryRiotAccountRepository([$original]);

        // ... et des données "fraîches" renvoyées par l'API : GOLD II, 50 LP, level 150
        $refreshData = new RiotAccountRefreshData(
            new SummonerRankedEntity(RankedRank::II, RankedTier::GOLD, 50, 40),
            150,
            '20'
        );
        $apiClient = new FakeRiotApiClient($refreshData);

        $presenter = new RefreshViewPresenter();
        $handler = new RefreshRiotAccountDataHandler($repository, $apiClient);

        // Act
        $handler->handle($presenter);

        // Assert : les données FRAÎCHES ont transité par le presenter
        $vm = $presenter->viewModel();
        $this->assertCount(1, $vm);
        $this->assertSame('Pseudo', $vm[0]->summonerName);
        $this->assertSame('GOLD', $vm[0]->tier);
        $this->assertSame('II', $vm[0]->rank);
        $this->assertSame(50, $vm[0]->leaguePoints);

    }
}
