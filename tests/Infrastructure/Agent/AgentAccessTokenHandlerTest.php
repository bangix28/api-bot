<?php

namespace App\Tests\Infrastructure\Agent;

use App\Domain\Agent\AgentToken;
use App\Domain\Agent\SecretDigest;
use App\Domain\Player\DiscordIdentity;
use App\Infrastructure\Security\AgentAccessTokenHandler;
use App\Infrastructure\Security\AgentAuthenticationResponder;
use App\Infrastructure\Security\AgentUser;
use App\Tests\Domain\Agent\InMemoryAgentTokenRepository;
use App\Tests\Domain\Shared\FixedClock;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class AgentAccessTokenHandlerTest extends TestCase
{
    private InMemoryAgentTokenRepository $tokens;

    protected function setUp(): void
    {
        $this->tokens = new InMemoryAgentTokenRepository();
        $this->tokens->saveReplacingPrevious(new AgentToken(
            SecretDigest::of('agt_valide'),
            new DiscordIdentity('1001', 'alpha'),
            3,
            new \DateTimeImmutable('2026-11-08 04:00:00'),
        ));
    }

    private function handlerAt(string $now): AgentAccessTokenHandler
    {
        return new AgentAccessTokenHandler($this->tokens, new FixedClock(new \DateTimeImmutable($now)));
    }

    public function testUnJetonValideDonneLAgent(): void
    {
        $agent = $this->handlerAt('2026-11-07 21:00')->getUserBadgeFrom('agt_valide')->getUser();

        $this->assertInstanceOf(AgentUser::class, $agent);
        $this->assertSame(['ROLE_AGENT'], $agent->getRoles());
        $this->assertSame(3, $agent->token->lanId);
        $this->assertStringNotContainsString('agt_valide', $agent->getUserIdentifier(), 'Le jeton ne doit jamais servir d\'identifiant.');
    }

    public function testUnJetonInconnuEstRefuse(): void
    {
        $this->expectException(BadCredentialsException::class);

        $this->handlerAt('2026-11-07 21:00')->getUserBadgeFrom('agt_inconnu');
    }

    public function testUnJwtNEstPasUnJetonDAgent(): void
    {
        $this->expectException(BadCredentialsException::class);

        $this->handlerAt('2026-11-07 21:00')->getUserBadgeFrom('eyJ0eXAiOiJKV1Qi');
    }

    public function testLeJetonResteAccepteDansLHeureDeGrace(): void
    {
        $this->assertInstanceOf(AgentUser::class, $this->handlerAt('2026-11-08 04:59')->getUserBadgeFrom('agt_valide')->getUser());
    }

    public function testApresLaGraceLeJetonEstExpire(): void
    {
        try {
            $this->handlerAt('2026-11-08 05:01')->getUserBadgeFrom('agt_valide');
            $this->fail('Un jeton hors grâce doit être refusé.');
        } catch (AuthenticationException $exception) {
            $response = (new AgentAuthenticationResponder())->onAuthenticationFailure(new Request(), $exception);

            $this->assertSame(401, $response->getStatusCode());
            $this->assertSame('token_expired', json_decode($response->getContent(), true)['code']);
        }
    }

    public function testUnJetonRefuseRepondInvalidToken(): void
    {
        $response = (new AgentAuthenticationResponder())->onAuthenticationFailure(new Request(), new BadCredentialsException());

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('invalid_token', json_decode($response->getContent(), true)['code']);
    }

    public function testSansJetonLaReponseEstInvalidToken(): void
    {
        $response = (new AgentAuthenticationResponder())->start(new Request());

        $this->assertSame('invalid_token', json_decode($response->getContent(), true)['code']);
    }
}
