<?php

namespace App\Application\RankedRace\ComputeEventStandings;

use App\Application\RankedRace\StandingsViewAssembler;
use App\Domain\RankedRace\RaceEventNotFoundException;
use App\Domain\RankedRace\RaceEventRepositoryInterface;
use App\Domain\RankedRace\RaceFreshness;
use App\Domain\RankedRace\RaceSnapshotRepositoryInterface;
use App\Domain\RankedRace\RaceStatus;
use App\Domain\Shared\ClockInterface;

final readonly class ComputeEventStandingsHandler
{
    public function __construct(
        private RaceEventRepositoryInterface $events,
        private RaceSnapshotRepositoryInterface $snapshots,
        private ClockInterface $clock,
        private StandingsViewAssembler $assembler = new StandingsViewAssembler(),
        // Même drapeau que la course calendaire : pendant les placements,
        // la Progression est masquée partout, le Winrate continue.
        private bool $progressionSuspended = false,
    ) {
    }

    public function handle(ComputeEventStandingsCommand $command): RankedRaceEventStandingsView
    {
        $event = $this->events->findById($command->eventId)
            ?? throw new RaceEventNotFoundException(sprintf('Événement %d introuvable', $command->eventId));

        // Un événement terminé garde son palmarès : les snapshots persistent,
        // le calcul sur sa fenêtre reste valable indéfiniment.
        $series = $this->assembler->groupByPlayer(
            $this->snapshots->findForWindow($event->queue, $event->window)
        );

        $now = $this->clock->now();
        $freshness = RaceFreshness::at($now, $this->snapshots->lastCapturedAt($event->queue));

        return new RankedRaceEventStandingsView(
            $event->id,
            $event->name,
            $event->queue->toQueryParam(),
            $event->window->startDate(),
            $event->window->endDate(),
            $event->statusAt($this->clock->today())->value,
            $event->minGamesToQualify,
            $this->progressionSuspended,
            $this->progressionSuspended ? [] : $this->assembler->progression($series, $event->queue, $event->window),
            $this->assembler->winrate($series, $event->minGamesToQualify),
            RaceStatus::of($event->window, $this->assembler->hasAnyGame($series), $now)->value,
            [
                'start' => $event->window->startsAt->format(\DateTimeInterface::ATOM),
                'endExclusive' => $event->window->endsAt->format(\DateTimeInterface::ATOM),
            ],
            $freshness->lastSnapshotAt?->format(\DateTimeInterface::ATOM),
            $freshness->nextRefreshAt?->format(\DateTimeInterface::ATOM),
            $freshness->generatedAt->format(\DateTimeInterface::ATOM),
            $freshness->snapshotAgeSeconds,
        );
    }
}
