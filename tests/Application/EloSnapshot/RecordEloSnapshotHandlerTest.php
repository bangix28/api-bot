<?php

namespace App\Tests\Application\EloSnapshot;

use App\Application\EloSnapshot\RecordEloSnapshot\RecordEloSnapshotHandler;
use App\Domain\EloSnapshot\RankedQueuesSnapshot;
use App\Domain\EloSnapshot\RankedQueueType;
use App\Domain\RiotAccount\RankedQueueEntity;
use App\Domain\RiotAccount\RankedRank;
use App\Domain\RiotAccount\RankedTier;
use App\Tests\Domain\EloSnapshot\InMemoryEloSnapshotWriteRepository;
use PHPUnit\Framework\TestCase;

class RecordEloSnapshotHandlerTest extends TestCase
{
    public function testSoloEtFlexProduisentChacunLeurPoint(): void
    {
        $repository = new InMemoryEloSnapshotWriteRepository();
        $handler = new RecordEloSnapshotHandler($repository);

        $handler->record(
            'puuid-1',
            new RankedQueuesSnapshot(
                new RankedQueueEntity(RankedRank::II, RankedTier::GOLD, 50, 10, 10),
                new RankedQueueEntity(RankedRank::IV, RankedTier::SILVER, 20, 5, 5),
            ),
            new \DateTimeImmutable('2026-08-03 10:00'),
        );

        $this->assertCount(2, $repository->all());
        $this->assertCount(1, $repository->forQueue(RankedQueueType::SOLO));
        $this->assertCount(1, $repository->forQueue(RankedQueueType::FLEX));
    }

    public function testUnCompteNonClasseNeProduitAucunPoint(): void
    {
        // L'absence de ligne est une information : pas de rang, pas de course.
        $repository = new InMemoryEloSnapshotWriteRepository();
        $handler = new RecordEloSnapshotHandler($repository);

        $handler->record(
            'puuid-1',
            new RankedQueuesSnapshot(
                new RankedQueueEntity(RankedRank::UNRANKED, RankedTier::UNRANKED, 0, 0, 0),
                null,
            ),
            new \DateTimeImmutable('2026-08-03 10:00'),
        );

        $this->assertSame([], $repository->all());
    }

    public function testUneFileFlexAbsenteEstIgnoreeSansToucherLaSolo(): void
    {
        $repository = new InMemoryEloSnapshotWriteRepository();
        $handler = new RecordEloSnapshotHandler($repository);

        $handler->record(
            'puuid-1',
            new RankedQueuesSnapshot(
                new RankedQueueEntity(RankedRank::II, RankedTier::GOLD, 50, 10, 10),
                null,
            ),
            new \DateTimeImmutable('2026-08-03 10:00'),
        );

        $this->assertCount(1, $repository->all());
        $this->assertSame([], $repository->forQueue(RankedQueueType::FLEX));
    }

    public function testDeuxPassagesSansChangementNEcriventQuUneFois(): void
    {
        // Le comportement qui évite 1 440 lignes/jour pour 15 comptes.
        $repository = new InMemoryEloSnapshotWriteRepository();
        $handler = new RecordEloSnapshotHandler($repository);
        $queues = new RankedQueuesSnapshot(
            new RankedQueueEntity(RankedRank::II, RankedTier::GOLD, 50, 10, 10),
            null,
        );

        $handler->record('puuid-1', $queues, new \DateTimeImmutable('2026-08-03 10:00'));
        $handler->record('puuid-1', $queues, new \DateTimeImmutable('2026-08-03 10:30'));

        $this->assertCount(1, $repository->all());
    }

    public function testUnePartieJoueeEntreDeuxPassagesEstEcrite(): void
    {
        $repository = new InMemoryEloSnapshotWriteRepository();
        $handler = new RecordEloSnapshotHandler($repository);

        $handler->record(
            'puuid-1',
            new RankedQueuesSnapshot(new RankedQueueEntity(RankedRank::II, RankedTier::GOLD, 50, 10, 10), null),
            new \DateTimeImmutable('2026-08-03 10:00'),
        );
        $handler->record(
            'puuid-1',
            new RankedQueuesSnapshot(new RankedQueueEntity(RankedRank::II, RankedTier::GOLD, 68, 11, 10), null),
            new \DateTimeImmutable('2026-08-03 10:30'),
        );

        $this->assertCount(2, $repository->all());
    }

    public function testLInstantEnregistreEstCeluiFourniParLAppelant(): void
    {
        // Et non un new \DateTimeImmutable() interne : sinon les tests seraient
        // non déterministes, et le fuseau du serveur reprendrait la main.
        $repository = new InMemoryEloSnapshotWriteRepository();
        $handler = new RecordEloSnapshotHandler($repository);
        $instant = new \DateTimeImmutable('2026-08-03 14:32:11');

        $handler->record(
            'puuid-1',
            new RankedQueuesSnapshot(new RankedQueueEntity(RankedRank::II, RankedTier::GOLD, 50, 10, 10), null),
            $instant,
        );

        $this->assertEquals($instant, $repository->all()[0]->capturedAt);
    }

    public function testLesComptesSontIsoles(): void
    {
        $repository = new InMemoryEloSnapshotWriteRepository();
        $handler = new RecordEloSnapshotHandler($repository);
        $queues = new RankedQueuesSnapshot(
            new RankedQueueEntity(RankedRank::II, RankedTier::GOLD, 50, 10, 10),
            null,
        );

        $handler->record('puuid-1', $queues, new \DateTimeImmutable('2026-08-03 10:00'));
        $handler->record('puuid-2', $queues, new \DateTimeImmutable('2026-08-03 10:00'));

        $this->assertCount(2, $repository->all());
    }
}
