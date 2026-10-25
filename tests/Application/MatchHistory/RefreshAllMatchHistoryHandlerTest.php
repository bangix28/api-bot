<?php

namespace App\Tests\Application\MatchHistory;

use App\Application\MatchHistory\RefreshData\RefreshAllMatchHistoryHandler;
use App\Application\MatchHistory\RefreshData\RefreshRiotMatchDataHandler;
use App\Domain\MatchHistory\MatchHistoryRefreshTarget;
use App\Domain\MatchHistory\MatchQueue;
use App\Tests\Domain\MatchHistory\FakeMatchHistoryRefreshTargetsProvider;
use App\Tests\Domain\MatchHistory\FakeRiotMatchApiClient;
use App\Tests\Domain\MatchHistory\InMemoryMatchHistoryRepository;
use App\Tests\Domain\MatchHistory\MatchDataBuilder;
use App\Tests\Domain\MatchHistory\ParticipantDataBuilder;
use PHPUnit\Framework\TestCase;

class RefreshAllMatchHistoryHandlerTest extends TestCase
{
    public function testRefreshesHistoryForEachTarget(): void
    {
        // Arrange : 2 comptes à rafraîchir
        $targetsProvider = new FakeMatchHistoryRefreshTargetsProvider([
            new MatchHistoryRefreshTarget('puuid-1', null),
            new MatchHistoryRefreshTarget('puuid-2', null),
        ]);

        // Le match canné contient les deux joueurs (la factory retrouvera le bon selon le puuid)
        $match = MatchDataBuilder::aMatch()
            ->withParticipantData(ParticipantDataBuilder::aParticipant()->withPuuid('puuid-1')->build())
            ->withParticipantData(ParticipantDataBuilder::aParticipant()->withPuuid('puuid-2')->build())
            ->build();

        $repository = new InMemoryMatchHistoryRepository();
        $perAccount = new RefreshRiotMatchDataHandler(new FakeRiotMatchApiClient($match), $repository);

        $handler = new RefreshAllMatchHistoryHandler($targetsProvider, $perAccount);

        // Act
        $handler->handle();

        // Assert : un historique sauvegardé par compte
        $saved = $repository->getListMatches();
        $this->assertCount(2, $saved);
    }

    public function testLesFilesDeLaCibleSontTransmisesALaCollecte(): void
    {
        $targetsProvider = new FakeMatchHistoryRefreshTargetsProvider([
            new MatchHistoryRefreshTarget('puuid-1', null, [MatchQueue::ARAM_MAYHEM]),
        ]);
        $apiClient = new FakeRiotMatchApiClient(
            MatchDataBuilder::aMatch()
                ->withParticipantData(ParticipantDataBuilder::aParticipant()->withPuuid('puuid-1')->build())
                ->build(),
        );

        (new RefreshAllMatchHistoryHandler(
            $targetsProvider,
            new RefreshRiotMatchDataHandler($apiClient, new InMemoryMatchHistoryRepository()),
        ))->handle();

        $this->assertSame([MatchQueue::ARAM_MAYHEM], $apiClient->queriedQueues);
    }
}
