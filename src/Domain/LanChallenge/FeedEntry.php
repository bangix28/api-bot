<?php

namespace App\Domain\LanChallenge;

/**
 * Une ligne du fil d'exploits de la LAN : une manche réglée, ou un sabotage
 * visible. Le saboteur n'y est jamais nommé : riotId désigne toujours le joueur
 * concerné (celui qui a joué la manche, ou la cible du sabotage).
 */
final readonly class FeedEntry
{
    private function __construct(
        public FeedEntryKind $kind,
        public string $riotId,
        public Challenge $card,
        public \DateTimeImmutable $occurredAt,
        public ?string $gameId = null,
        public ?Verdict $verdict = null,
        public int $points = 0,
        public bool $roundBonus = false,
        public bool $curseApplied = false,
    ) {
    }

    public static function round(string $riotId, RoundResult $result, \DateTimeImmutable $settledAt): self
    {
        return new self(
            FeedEntryKind::ROUND, $riotId, $result->card, $settledAt,
            $result->gameId, $result->verdict, $result->points, $result->roundBonus, $result->curseApplied,
        );
    }

    public static function sabotage(string $targetRiotId, Challenge $card, \DateTimeImmutable $occurredAt): self
    {
        return new self(FeedEntryKind::SABOTAGE, $targetRiotId, $card, $occurredAt);
    }
}
