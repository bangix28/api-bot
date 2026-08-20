<?php

namespace App\Application\RankedRace\AuditSegments;

use App\Application\RankedRace\StandingsViewAssembler;
use App\Domain\EloSnapshot\RankedQueueType;
use App\Domain\RankedRace\InvalidRankedRaceParameterException;
use App\Domain\RankedRace\PlayerRaceSeries;
use App\Domain\RankedRace\RacePeriod;
use App\Domain\RankedRace\RaceSegment;
use App\Domain\RankedRace\RaceSnapshot;
use App\Domain\RankedRace\RaceSnapshotRepositoryInterface;
use App\Domain\Shared\ClockInterface;

/**
 * Décompose le score de course jusqu'au segment qui l'a produit.
 *
 * N'ajoute AUCUNE règle : il relit le même port et le même domaine que le
 * classement, et se contente de rendre visible ce qui était déjà calculé. C'est
 * ce qui en fait un instrument de mesure fiable — un audit qui empruntait un
 * autre chemin de lecture pourrait dire vrai pendant que l'API dit faux.
 */
final readonly class AuditRaceSegmentsHandler
{
    public function __construct(
        private RaceSnapshotRepositoryInterface $snapshots,
        private ClockInterface $clock,
        private StandingsViewAssembler $assembler = new StandingsViewAssembler(),
    ) {
    }

    /** @return PlayerAuditView[] */
    public function handle(AuditRaceSegmentsCommand $command): array
    {
        $queue = RankedQueueType::tryFromQueryParam($command->queue)
            ?? throw new InvalidRankedRaceParameterException(
                sprintf('File invalide : "%s" (attendu : solo ou flex)', $command->queue)
            );
        $period = RacePeriod::fromQueryParam($command->period);
        $window = $period->windowFor($this->clock->today());

        $series = $this->assembler->groupByPlayer($this->snapshots->findForWindow($queue, $window));

        if ($command->riotId !== null) {
            $series = array_filter(
                $series,
                static fn(PlayerRaceSeries $s) => $s->player()->riotId === $command->riotId,
            );
        }

        $audits = [];
        foreach ($series as $playerSeries) {
            $audits[] = self::audit($playerSeries);
        }

        return $audits;
    }

    private static function audit(PlayerRaceSeries $series): PlayerAuditView
    {
        $segments = [];
        $crossings = 0;

        foreach ($series->segments() as $segment) {
            $view = self::mapSegment($segment);
            $segments[] = $view;

            if ($view->tierCrossing !== null) {
                $crossings++;
            }
        }

        $raw = $series->rawProgression();
        $weighted = $series->weightedProgression();

        return new PlayerAuditView(
            $series->player()->riotId,
            $series->player()->summonerName,
            self::rank($series->baseline()),
            self::rank($series->finish()),
            $raw,
            $weighted,
            // Une progression brute nulle ne donne pas un rapport de 0 : elle
            // n'en donne aucun. Diviser produirait une division par zéro ou,
            // pire, un 0.0 qui passerait pour une mesure.
            $raw === 0 ? null : round($weighted / $raw, 3),
            $series->gamesPlayed(),
            $series->offRaceDelta(),
            $crossings,
            $segments,
        );
    }

    private static function mapSegment(RaceSegment $segment): SegmentAuditView
    {
        return new SegmentAuditView(
            $segment->from->capturedAt->format('Y-m-d H:i'),
            $segment->to->capturedAt->format('Y-m-d H:i'),
            self::rank($segment->from),
            self::rank($segment->to),
            $segment->games(),
            $segment->wins(),
            $segment->scoreDelta(),
            round($segment->weightedDelta(), 2),
            self::crossing($segment),
        );
    }

    private static function crossing(RaceSegment $segment): ?string
    {
        $from = self::band($segment->from);
        $to = self::band($segment->to);

        return $from === $to ? null : sprintf('%s → %s', $from, $to);
    }

    /** Bande d'échelle, apex confondu : M/GM/Challenger partagent le même plancher. */
    private static function band(RaceSnapshot $snapshot): string
    {
        $tier = $snapshot->ranked->getTier();

        return $tier->isApex() ? 'MASTER+' : $tier->value;
    }

    private static function rank(RaceSnapshot $snapshot): string
    {
        $ranked = $snapshot->ranked;
        $tier = $ranked->getTier();

        return $tier->isApex()
            ? sprintf('%s %d LP', $tier->value, $ranked->getLeaguePoints())
            : sprintf('%s %s %d LP', $tier->value, $ranked->getDivision()->value, $ranked->getLeaguePoints());
    }
}
