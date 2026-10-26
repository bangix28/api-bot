<?php

namespace App\Infrastructure\Security;

use App\Domain\Agent\AgentTokenRepositoryInterface;
use App\Domain\Shared\ClockInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Reconnaît l'agent à son jeton « agt_… » (Authorization: Bearer).
 *
 * Un jeton est accepté jusqu'à la fin de sa LAN plus l'heure de grâce du relevé
 * final ; les actions qui ne bénéficient pas de cette grâce (session, relevés
 * intermédiaires) la refusent elles-mêmes.
 */
final readonly class AgentAccessTokenHandler implements AccessTokenHandlerInterface
{
    public const string EXPIRED = 'token_expired';

    public function __construct(
        private AgentTokenRepositoryInterface $tokens,
        private ClockInterface $clock,
    ) {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        $token = $this->tokens->findActive($accessToken)
            ?? throw new BadCredentialsException('Jeton d\'agent inconnu ou révoqué.');

        if (!$token->acceptsFinalReportAt($this->clock->now())) {
            throw new CustomUserMessageAuthenticationException(self::EXPIRED);
        }

        $agent = new AgentUser($token);

        return new UserBadge($agent->getUserIdentifier(), static fn() => $agent);
    }
}
