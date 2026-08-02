<?php

namespace App\DataFixtures;

use App\Domain\RiotAccount\RankedQueueEntity;
use App\Domain\RiotAccount\RankedRank;
use App\Domain\RiotAccount\RankedTier;

/**
 * Position sur l'échelle ranked pour générer des séries de snapshots réalistes :
 * applique des deltas de LP avec promotion/rétrogradation, sans jamais produire
 * un état invalide (LP hors bornes — RankedQueueEntity le validerait de toute façon).
 */
final class LadderPosition
{
    private const array TIERS = [
        RankedTier::IRON,
        RankedTier::BRONZE,
        RankedTier::SILVER,
        RankedTier::GOLD,
        RankedTier::PLATINUM,
        RankedTier::EMERALD,
        RankedTier::DIAMOND,
        RankedTier::MASTER,
    ];

    /** Ordre montant : IV -> I. */
    private const array DIVISIONS = [RankedRank::IV, RankedRank::III, RankedRank::II, RankedRank::I];

    private int $tierIndex;
    private int $divisionIndex;
    private int $lp;

    public function __construct(RankedTier $tier, RankedRank $division, int $lp)
    {
        $this->tierIndex = (int) array_search($tier, self::TIERS, true);
        $this->divisionIndex = (int) array_search($division, self::DIVISIONS, true);
        $this->lp = $lp;
    }

    public function applyLpDelta(int $delta): void
    {
        // Apex : pas de divisions, LP libres au-dessus du plancher Master.
        if ($this->isApex()) {
            $this->lp = max(0, $this->lp + $delta);

            return;
        }

        $lp = $this->lp + $delta;

        while ($lp > 99) {
            $lp -= 100;
            $this->promote();

            if ($this->isApex()) {
                break;
            }
        }

        while ($lp < 0) {
            if ($this->tierIndex === 0 && $this->divisionIndex === 0) {
                $lp = 0; // plancher Iron IV 0 LP
                break;
            }

            $lp += 100;
            $this->demote();
        }

        $this->lp = max(0, $lp);
    }

    public function toRankedQueue(int $wins, int $losses): RankedQueueEntity
    {
        return new RankedQueueEntity(
            $this->isApex() ? RankedRank::UNRANKED : self::DIVISIONS[$this->divisionIndex],
            self::TIERS[$this->tierIndex],
            $this->lp,
            $wins,
            $losses,
        );
    }

    private function isApex(): bool
    {
        return self::TIERS[$this->tierIndex] === RankedTier::MASTER;
    }

    private function promote(): void
    {
        if ($this->divisionIndex < 3) {
            $this->divisionIndex++;

            return;
        }

        $this->tierIndex = min($this->tierIndex + 1, count(self::TIERS) - 1);
        $this->divisionIndex = 0;
    }

    private function demote(): void
    {
        if ($this->divisionIndex > 0) {
            $this->divisionIndex--;

            return;
        }

        $this->tierIndex = max($this->tierIndex - 1, 0);
        $this->divisionIndex = 3;
    }
}
