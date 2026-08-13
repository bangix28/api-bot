<?php

namespace App\Tests\Application\RiotAccount;

use App\Application\RiotAccount\RefreshData\RefreshRiotAccountDataHandler;
use App\Domain\RiotAccount\MiniSeries;
use App\Domain\RiotAccount\RankedQueueEntity;
use App\Domain\RiotAccount\RankedRank;
use App\Domain\RiotAccount\RankedTier;
use App\Domain\RiotAccount\RiotAccountEntity;
use App\Domain\RiotAccount\RiotAccountNotExistException;
use App\Domain\RiotAccount\RiotAccountRefreshData;
use App\Infrastructure\RiotAccount\RefreshViewPresenter;
use App\Tests\Domain\Logging\SpyLogger;
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
            new RankedQueueEntity(RankedRank::UNRANKED, RankedTier::UNRANKED, 0, 0, 0),
            30,
            '10'
        );
        $repository = new InMemoryRiotAccountRepository([$original]);

        // ... et des données "fraîches" renvoyées par l'API : GOLD II, 50 LP, level 150
        $refreshData = new RiotAccountRefreshData(
            new RankedQueueEntity(RankedRank::II, RankedTier::GOLD, 50, 40, 20),
            null,
            150,
            '20'
        );
        $apiClient = new FakeRiotApiClient($refreshData);

        $handler = new RefreshRiotAccountDataHandler($repository, $apiClient);

        // Act
        $handler->handle(new RefreshViewPresenter());

        // Assert : les données ranked/level/logo ont été rafraîchies
        $updated = $repository->getListAccount()[0];
        $this->assertSame(RankedTier::GOLD, $updated->getRankedSolo()->getTier());
        $this->assertSame(RankedRank::II, $updated->getRankedSolo()->getDivision());
        $this->assertSame(50, $updated->getRankedSolo()->getLeaguePoints());
        $this->assertSame(150, $updated->getSummonerLevel());
        $this->assertSame('20', $updated->getLogoId());
        $this->assertNull($updated->getRankedFlex());

        // ... et l'identité est préservée (vient de l'entité d'origine, pas du DTO)
        $this->assertSame('Pseudo#EUW', $updated->getRiotID());
        $this->assertSame('puuid-1', $updated->getPuuid());
        $this->assertSame('Pseudo', $updated->getSummonerName());
    }

    public function testHandleUpdatesAccountWithFlexAndSoloFlags(): void
    {
        $original = new RiotAccountEntity(
            'Pseudo#EUW',
            'puuid-1',
            'Pseudo',
            new RankedQueueEntity(RankedRank::UNRANKED, RankedTier::UNRANKED, 0, 0, 0),
            30,
            '10'
        );
        $repository = new InMemoryRiotAccountRepository([$original]);

        // SoloQ en série de victoires + Bo5 en cours, et un rang Flex distinct
        $refreshData = new RiotAccountRefreshData(
            new RankedQueueEntity(
                RankedRank::I, RankedTier::GOLD, 99, 40, 20,
                hotStreak: true,
                veteran: false,
                freshBlood: true,
                miniSeries: new MiniSeries(2, 1, 3, 'WLW'),
            ),
            new RankedQueueEntity(RankedRank::IV, RankedTier::SILVER, 10, 12, 8),
            150,
            '20'
        );
        $apiClient = new FakeRiotApiClient($refreshData);

        $handler = new RefreshRiotAccountDataHandler($repository, $apiClient);
        $handler->handle(new RefreshViewPresenter());

        $updated = $repository->getListAccount()[0];

        $solo = $updated->getRankedSolo();
        $this->assertTrue($solo->isHotStreak());
        $this->assertFalse($solo->isVeteran());
        $this->assertTrue($solo->isFreshBlood());
        $this->assertSame(2, $solo->getMiniSeries()->wins);
        $this->assertSame('WLW', $solo->getMiniSeries()->progress);

        $flex = $updated->getRankedFlex();
        $this->assertNotNull($flex);
        $this->assertSame(RankedTier::SILVER, $flex->getTier());
        $this->assertSame(RankedRank::IV, $flex->getDivision());
        $this->assertSame(10, $flex->getLeaguePoints());
    }

    public function testHandleUpdatesAccountSendToPresenter()
    {
        // Arrange : un compte "original" non classé, level 30
        $original = new RiotAccountEntity(
            'Pseudo#EUW',
            'puuid-1',
            'Pseudo',
            new RankedQueueEntity(RankedRank::UNRANKED, RankedTier::UNRANKED, 0, 0, 0),
            30,
            '10'
        );
        $repository = new InMemoryRiotAccountRepository([$original]);

        // ... et des données "fraîches" renvoyées par l'API : GOLD II, 50 LP, level 150
        $refreshData = new RiotAccountRefreshData(
            new RankedQueueEntity(RankedRank::II, RankedTier::GOLD, 50, 40, 20),
            null,
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

    public function testHandleContinuesAndLogsWhenOneAccountFails()
    {
        $original = new RiotAccountEntity(
            'Pseudo#EUW',
            'puuid-1',
            'Pseudo',
            new RankedQueueEntity(RankedRank::UNRANKED, RankedTier::UNRANKED, 0, 0, 0),
            30,
            '10'
        );

        $failedAccount = new RiotAccountEntity(
            'Fake#EUW',
            'fake',
            'Pseudo',
            new RankedQueueEntity(RankedRank::UNRANKED, RankedTier::UNRANKED, 0, 0, 0),
            25,
            '10'
        );

        $repository = new InMemoryRiotAccountRepository([$original, $failedAccount]);

        $refreshData = new RiotAccountRefreshData(
            new RankedQueueEntity(RankedRank::II, RankedTier::GOLD, 50, 40, 20),
            null,
            150,
            '20'
        );
        // Un seul fake : données fraîches pour tout le monde, sauf le puuid 'fake' qui jette
        $apiClient = new FakeRiotApiClient($refreshData, 'fake');

        $presenter = new RefreshViewPresenter();
        $logger = new SpyLogger();
        $handler = new RefreshRiotAccountDataHandler($repository, $apiClient, $logger);

        // Act
        $handler->handle($presenter);

        $vm = $presenter->viewModel();
        $this->assertCount(1, $vm);
        $this->assertSame('Pseudo', $vm[0]->summonerName);
        $this->assertSame('GOLD', $vm[0]->tier);
        $this->assertSame('II', $vm[0]->rank);
        $this->assertSame(50, $vm[0]->leaguePoints);

        $accounts = $repository->getListAccount();
        $this->assertCount(2, $accounts);
        $this->assertSame(RankedTier::GOLD, $accounts[0]->getRankedSolo()->getTier());
        $this->assertSame(RankedTier::UNRANKED, $accounts[1]->getRankedSolo()->getTier());

        $warnings = $logger->records('warning');
        $this->assertCount(1, $warnings);
        $this->assertSame('Refresh du compte ignoré', $warnings[0]['message']);
        $this->assertSame('fake', $warnings[0]['context']['puuid']);

        $infos = $logger->records('info');
        $this->assertCount(1, $infos);
        $this->assertSame('Refresh des comptes terminé', $infos[0]['message']);
        $this->assertSame(1, $infos[0]['context']['ok']);
        $this->assertSame(1, $infos[0]['context']['failed']);
    }

    public function testHandleOneRefreshesOnlyTheTargetAccount(): void
    {
        // Arrange : deux comptes non classés
        $repository = new InMemoryRiotAccountRepository([
            $this->unrankedAccount('Pseudo#EUW', 'puuid-1'),
            $this->unrankedAccount('Autre#EUW', 'puuid-2'),
        ]);

        $refreshData = new RiotAccountRefreshData(
            new RankedQueueEntity(RankedRank::II, RankedTier::GOLD, 50, 40, 20),
            null,
            150,
            '20'
        );
        $handler = new RefreshRiotAccountDataHandler($repository, new FakeRiotApiClient($refreshData));

        // Act : on n'enrichit que le premier compte
        $handler->handleOne('puuid-1');

        // Assert : le second n'a pas été touché
        $accounts = $repository->getListAccount();
        $this->assertSame(RankedTier::GOLD, $accounts[0]->getRankedSolo()->getTier());
        $this->assertSame(150, $accounts[0]->getSummonerLevel());
        $this->assertSame(RankedTier::UNRANKED, $accounts[1]->getRankedSolo()->getTier());
        $this->assertSame(30, $accounts[1]->getSummonerLevel());
    }

    public function testHandleOneThrowsWhenAccountIsUnknown(): void
    {
        $repository = new InMemoryRiotAccountRepository([$this->unrankedAccount('Pseudo#EUW', 'puuid-1')]);

        $refreshData = new RiotAccountRefreshData(
            new RankedQueueEntity(RankedRank::II, RankedTier::GOLD, 50, 40, 20),
            null,
            150,
            '20'
        );
        $handler = new RefreshRiotAccountDataHandler($repository, new FakeRiotApiClient($refreshData));

        $this->expectException(RiotAccountNotExistException::class);
        $handler->handleOne('puuid-inconnu');
    }

    public function testHandleOneLetsRiotFailureBubbleUp(): void
    {
        // Le contrat dont dépend l'adaptateur admin : handleOne ne rattrape rien,
        // c'est l'appelant qui décide du best effort.
        $repository = new InMemoryRiotAccountRepository([$this->unrankedAccount('Pseudo#EUW', 'puuid-1')]);

        $refreshData = new RiotAccountRefreshData(
            new RankedQueueEntity(RankedRank::II, RankedTier::GOLD, 50, 40, 20),
            null,
            150,
            '20'
        );
        $handler = new RefreshRiotAccountDataHandler($repository, new FakeRiotApiClient($refreshData, 'puuid-1'));

        $this->expectException(\RuntimeException::class);
        $handler->handleOne('puuid-1');
    }

    private function unrankedAccount(string $riotId, string $puuid): RiotAccountEntity
    {
        return new RiotAccountEntity(
            $riotId,
            $puuid,
            'Pseudo',
            new RankedQueueEntity(RankedRank::UNRANKED, RankedTier::UNRANKED, 0, 0, 0),
            30,
            '10'
        );
    }
}
