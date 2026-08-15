<?php

namespace App\Application\RankedRace\ComputeStandings;

use App\Application\RankedRace\StandingsViewAssembler;
use App\Domain\EloSnapshot\RankedQueueType;
use App\Domain\RankedRace\InvalidRankedRaceParameterException;
use App\Domain\RankedRace\RaceFreshness;
use App\Domain\RankedRace\RacePeriod;
use App\Domain\RankedRace\RaceSnapshotRepositoryInterface;
use App\Domain\RankedRace\RaceStatus;
use App\Domain\Shared\ClockInterface;

final readonly class ComputeRankedRaceStandingsHandler
{
    public function __construct(
        private RaceSnapshotRepositoryInterface $snapshots,
        private ClockInterface $clock,
        private StandingsViewAssembler $assembler = new StandingsViewAssembler(),
        // Levé manuellement pendant la fenêtre de placements de début de saison
        // (les deltas de progression y seraient absurdes). Le winrate, lui, continue.
        private bool $progressionSuspended = false,
    ) {
    }

    public function handle(ComputeRankedRaceStandingsCommand $command): RankedRaceStandingsView
    {
        $queue = RankedQueueType::tryFromQueryParam($command->queue)
            ?? throw new InvalidRankedRaceParameterException(
                sprintf('File invalide : "%s" (attendu : solo ou flex)', $command->queue)
            );
        $period = RacePeriod::fromQueryParam($command->period);

        $now = $this->clock->now();
        $window = $period->windowFor($this->clock->today());
        $series = $this->assembler->groupByPlayer($this->snapshots->findForWindow($queue, $window));
        $freshness = RaceFreshness::at($now, $this->snapshots->lastCapturedAt($queue));

        return new RankedRaceStandingsView(
            $queue->toQueryParam(),
            $period->value,
            $window->startDate(),
            $window->endDate(),
            $this->progressionSuspended,
            $this->progressionSuspended ? [] : $this->assembler->progression($series),
            $this->assembler->winrate($series, $period->minGamesToQualify()),
            // Le statut ne dépend pas de la suspension : une course peut être
            // « running » avec la Progression masquée pendant les placements.
            RaceStatus::of($window, $this->assembler->hasAnyGame($series), $now)->value,
            [
                'start' => $window->startsAt->format(\DateTimeInterface::ATOM),
                'endExclusive' => $window->endsAt->format(\DateTimeInterface::ATOM),
            ],
            $freshness->lastSnapshotAt?->format(\DateTimeInterface::ATOM),
            $freshness->nextRefreshAt?->format(\DateTimeInterface::ATOM),
            $freshness->generatedAt->format(\DateTimeInterface::ATOM),
            $freshness->snapshotAgeSeconds,
        );
    }
}
