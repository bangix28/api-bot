<?php

namespace App\Application\RankedRace\ComputeEventStandings;

use App\Application\RankedRace\StandingsViewAssembler;
use App\Domain\RankedRace\RaceEventNotFoundException;
use App\Domain\RankedRace\RaceEventRepositoryInterface;
use App\Domain\RankedRace\RaceSnapshotRepositoryInterface;
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

        return new RankedRaceEventStandingsView(
            $event->id,
            $event->name,
            $event->queue->toQueryParam(),
            $event->window->start->format('Y-m-d'),
            $event->window->end->format('Y-m-d'),
            $event->statusAt($this->clock->today())->value,
            $event->minGamesToQualify,
            $this->progressionSuspended,
            $this->progressionSuspended ? [] : $this->assembler->progression($series),
            $this->assembler->winrate($series, $event->minGamesToQualify),
        );
    }
}
