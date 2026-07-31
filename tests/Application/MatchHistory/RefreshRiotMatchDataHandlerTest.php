<?php

namespace App\Tests\Application\MatchHistory;

use App\Application\MatchHistory\RefreshData\RefreshMatchHistoryCommand;
use App\Application\MatchHistory\RefreshData\RefreshRiotMatchDataHandler;
use App\Tests\Domain\MatchHistory\FakeRiotMatchApiClient;
use App\Tests\Domain\MatchHistory\InMemoryMatchHistoryRepository;
use App\Tests\Domain\MatchHistory\MatchDataBuilder;
use App\Tests\Domain\MatchHistory\ParticipantDataBuilder;
use PHPUnit\Framework\TestCase;

class RefreshRiotMatchDataHandlerTest extends TestCase
{

    public function testRefreshRiotMatchData()
    {
        $inMemoryMatchHistoryRepository = new InMemoryMatchHistoryRepository();

        $apiClient = new FakeRiotMatchApiClient(
            MatchDataBuilder::aMatch()
            ->withGameDuration(15000)
            ->withParticipantData(
                ParticipantDataBuilder::aParticipant()->build()
            )->build()
        );

        $refreshRiotMatchDataHandler = new RefreshRiotMatchDataHandler($apiClient, $inMemoryMatchHistoryRepository);
        $refreshMatchHistoryCommand = new RefreshMatchHistoryCommand('puuid-1', 170000);
        $refreshRiotMatchDataHandler->handle($refreshMatchHistoryCommand);

        $listMatches = $inMemoryMatchHistoryRepository->getListMatches();
        $this->assertCount(1, $listMatches);
    }

    public function testSkipsMatchAlreadyStoredWithoutCallingRiot()
    {
        $inMemoryMatchHistoryRepository = new InMemoryMatchHistoryRepository();

        // Le fake annonce toujours le même matchId ('match-1') et renvoie un match qui porte cet id
        $apiClient = new FakeRiotMatchApiClient(
            MatchDataBuilder::aMatch()
                ->withMatchId('match-1')
                ->withParticipantData(
                    ParticipantDataBuilder::aParticipant()->build()
                )->build()
        );

        $handler = new RefreshRiotMatchDataHandler($apiClient, $inMemoryMatchHistoryRepository);
        $command = new RefreshMatchHistoryCommand('puuid-1', 170000);

        // 1er refresh : le match est téléchargé puis sauvegardé
        $handler->handle($command);
        // 2e refresh : le match est déjà en base → aucun nouvel appel Riot, aucune insertion
        $handler->handle($command);

        $this->assertCount(1, $inMemoryMatchHistoryRepository->getListMatches());
        $this->assertSame(1, $apiClient->getMatchCallCount);
    }

}
