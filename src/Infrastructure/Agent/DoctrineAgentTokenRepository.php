<?php

namespace App\Infrastructure\Agent;

use App\Domain\Agent\AgentToken;
use App\Domain\Agent\AgentTokenRepositoryInterface;
use App\Domain\Agent\SecretDigest;
use App\Domain\Player\DiscordIdentity;
use App\Domain\Shared\ClockInterface;
use App\Entity\AgentAccessToken;
use App\Entity\LanEvent;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineAgentTokenRepository implements AgentTokenRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function saveReplacingPrevious(AgentToken $token): void
    {
        $previousTokens = $this->entityManager->getRepository(AgentAccessToken::class)->findBy([
            'discordId' => $token->player->userId,
            'lanEvent' => $token->lanId,
            'revokedAt' => null,
        ]);
        foreach ($previousTokens as $previousToken) {
            $previousToken->revoke($this->clock->now());
        }

        $this->entityManager->persist(new AgentAccessToken(
            $token->digest,
            $token->player->userId,
            $token->player->username,
            $this->entityManager->getReference(LanEvent::class, $token->lanId),
            $token->expiresAt,
        ));
        $this->entityManager->flush();
    }

    public function findActive(string $plainToken): ?AgentToken
    {
        $row = $this->entityManager->getRepository(AgentAccessToken::class)
            ->findOneBy(['digest' => SecretDigest::of($plainToken), 'revokedAt' => null]);

        return $row === null ? null : new AgentToken(
            $row->getDigest(),
            new DiscordIdentity($row->getDiscordId(), $row->getDiscordUsername()),
            $row->getLanEvent()->getId(),
            $row->getExpiresAt(),
        );
    }
}
