<?php

namespace App\Tests\Domain\LanChallenge;

use App\Domain\LanChallenge\FeedEntry;
use App\Domain\LanChallenge\LanFeedQueryInterface;

final class InMemoryLanFeedQuery implements LanFeedQueryInterface
{
    /** Dernière limite demandée : le plafonnement se décide avant la requête. */
    public ?int $requestedLimit = null;

    /** @param array<int, FeedEntry[]> $entriesByLan déjà du plus récent au plus ancien */
    public function __construct(private readonly array $entriesByLan = [])
    {
    }

    /** @return FeedEntry[] */
    public function latest(int $lanId, int $limit): array
    {
        $this->requestedLimit = $limit;

        return array_slice($this->entriesByLan[$lanId] ?? [], 0, $limit);
    }
}
