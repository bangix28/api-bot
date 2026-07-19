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

}