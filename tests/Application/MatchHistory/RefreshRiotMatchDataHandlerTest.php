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

    public function testLesDeuxFilesClasseesSontInterrogees()
    {
        // La collecte ne connaissait que la solo : aucun match flex n'entrait en
        // base, ce qui rendait toute règle fondée sur les matchs aveugle à la flex.
        $apiClient = new FakeRiotMatchApiClient(
            MatchDataBuilder::aMatch()
                ->withParticipantData(ParticipantDataBuilder::aParticipant()->build())
                ->build()
        );

        (new RefreshRiotMatchDataHandler($apiClient, new InMemoryMatchHistoryRepository()))
            ->handle(new RefreshMatchHistoryCommand('puuid-1', 170000));

        $this->assertSame(['RANKED_SOLO_5x5', 'RANKED_FLEX_SR'], $apiClient->queriedQueues);
    }

    public function testLesMatchsDesDeuxFilesSontEnregistres()
    {
        $repository = new InMemoryMatchHistoryRepository();
        $apiClient = new FakeRiotMatchApiClient(
            MatchDataBuilder::aMatch()
                ->withParticipantData(ParticipantDataBuilder::aParticipant()->build())
                ->build(),
            ['RANKED_SOLO_5x5' => ['solo-1'], 'RANKED_FLEX_SR' => ['flex-1']],
        );

        (new RefreshRiotMatchDataHandler($apiClient, $repository))
            ->handle(new RefreshMatchHistoryCommand('puuid-1', 170000));

        $this->assertCount(2, $repository->getListMatches());
    }

    public function testUneFileEnEchecNInterromptPasLAutre()
    {
        // ADR-0002 : le best effort vaut aussi entre les files.
        $repository = new InMemoryMatchHistoryRepository();
        $apiClient = new FakeRiotMatchApiClient(
            MatchDataBuilder::aMatch()
                ->withParticipantData(ParticipantDataBuilder::aParticipant()->build())
                ->build(),
            ['RANKED_SOLO_5x5' => ['match-corrompu', 'solo-1'], 'RANKED_FLEX_SR' => ['flex-1']],
            failingMatchId: 'match-corrompu',
        );

        (new RefreshRiotMatchDataHandler($apiClient, $repository))
            ->handle(new RefreshMatchHistoryCommand('puuid-1', 170000));

        $this->assertCount(2, $repository->getListMatches());
    }
}
