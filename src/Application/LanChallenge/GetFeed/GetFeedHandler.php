<?php

namespace App\Application\LanChallenge\GetFeed;

use App\Domain\LanChallenge\FeedEntry;
use App\Domain\LanChallenge\LanFeedQueryInterface;
use App\Domain\LanChallenge\LanNotFoundException;
use App\Domain\LanChallenge\LanSummaryReaderInterface;

/** Les dernières manches réglées de la LAN, pour le fil d'exploits de l'écran. */
final readonly class GetFeedHandler
{
    public const int DEFAULT_LIMIT = 20;
    public const int MAX_LIMIT = 50;

    public function __construct(
        private LanSummaryReaderInterface $lans,
        private LanFeedQueryInterface $feed,
    ) {
    }

    /** @return FeedEntry[] */
    public function handle(int $lanId, int $limit = self::DEFAULT_LIMIT): array
    {
        if ($this->lans->find($lanId) === null) {
            throw new LanNotFoundException(sprintf('Aucune LAN pour l\'identifiant %d.', $lanId));
        }

        return $this->feed->latest($lanId, max(1, min($limit, self::MAX_LIMIT)));
    }
}
