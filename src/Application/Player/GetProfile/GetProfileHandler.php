<?php

namespace App\Application\Player\GetProfile;

use App\Domain\Player\DiscordIdentity;
use App\Domain\Player\TrackedRiotAccount;
use App\Domain\Player\TrackedRiotAccountRepositoryInterface;

final readonly class GetProfileHandler
{
    public function __construct(private TrackedRiotAccountRepositoryInterface $accounts)
    {
    }

    public function handle(DiscordIdentity $player): PlayerProfileView
    {
        return new PlayerProfileView(
            $player->userId,
            $player->username,
            array_map(static fn(TrackedRiotAccount $account) => $account->riotId, $this->accounts->findOwnedBy($player->userId)),
        );
    }
}
