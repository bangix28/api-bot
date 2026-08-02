<?php

namespace App\Application\EloSnapshot\SnapshotDailyElo;

use App\Domain\EloSnapshot\DailyEloSnapshot;
use App\Domain\EloSnapshot\EloSnapshotRepositoryInterface;
use App\Domain\EloSnapshot\RankedQueuesProviderInterface;
use App\Domain\EloSnapshot\RankedQueueType;
use App\Domain\RiotAccount\RankedQueueEntity;
use App\Domain\RiotAccount\RankedTier;
use App\Domain\RiotAccount\RiotAccountRepositoryInterface;
use App\Domain\Shared\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Snapshot quotidien de l'elo : une ligne par compte, par jour et par file
 * classée (solo + flex). Un compte non classé dans une file n'a pas de ligne —
 * l'absence est une info (règle Ranked Race : pas de score = pas de course).
 */
final readonly class SnapshotDailyEloHandler
{
    public function __construct(
        private RiotAccountRepositoryInterface $riotAccounts,
        private RankedQueuesProviderInterface $rankedQueues,
        private EloSnapshotRepositoryInterface $snapshots,
        private ClockInterface $clock,
        private LoggerInterface $refreshLogger = new NullLogger(),
    ) {
    }

    public function handleAll(): SnapshotSummary
    {
        $ok = $failed = $skipped = 0;

        foreach ($this->riotAccounts->getListAccount() as $account) {
            try {
                $this->snapshotAccount($account->getPuuid()) ? $ok++ : $skipped++;
            } catch (\Exception $e) {
                // L'échec d'un compte (erreur API, etc.) ne doit pas interrompre
                // le snapshot des autres comptes (ADR-0002).
                $failed++;
                $this->refreshLogger->warning('Snapshot elo quotidien ignoré pour un compte', [
                    'puuid' => $account->getPuuid(),
                    'exception' => $e,
                ]);
            }
        }

        $this->refreshLogger->info('Snapshot elo quotidien terminé', [
            'ok' => $ok,
            'failed' => $failed,
            'skipped' => $skipped,
        ]);

        return new SnapshotSummary($ok, $failed, $skipped);
    }

    /**
     * Snapshot d'un seul compte — utilisé à l'inscription pour que le joueur
     * entre dans la course dès son premier jour, sans attendre le cron.
     */
    public function handleOne(string $puuid): void
    {
        $this->snapshotAccount($puuid);
    }

    /** @return bool true si au moins une ligne a été créée, false si déjà fait ou non classé */
    private function snapshotAccount(string $puuid): bool
    {
        $today = $this->clock->today();

        // Idempotence : déjà snapshoté aujourd'hui -> pas d'appel Riot.
        if ($this->snapshots->existsFor($puuid, $today)) {
            return false;
        }

        $queues = $this->rankedQueues->getRankedQueues($puuid);

        $created = false;
        foreach ([[RankedQueueType::SOLO, $queues->solo], [RankedQueueType::FLEX, $queues->flex]] as [$queue, $ranked]) {
            if (!$this->isRanked($ranked)) {
                continue;
            }

            $this->snapshots->add(new DailyEloSnapshot($puuid, $today, $queue, $ranked));
            $created = true;
        }

        if (!$created) {
            // Trou dans les données quotidiennes : à surveiller, sinon il est invisible.
            $this->refreshLogger->warning('Snapshot elo quotidien non créé (compte non classé)', [
                'puuid' => $puuid,
            ]);
        }

        return $created;
    }

    private function isRanked(?RankedQueueEntity $ranked): bool
    {
        return $ranked !== null && $ranked->getTier() !== RankedTier::UNRANKED;
    }
}
