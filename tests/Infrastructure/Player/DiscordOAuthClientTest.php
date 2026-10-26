<?php

namespace App\Tests\Infrastructure\Player;

use App\Domain\Player\DiscordLoginFailedException;
use App\Infrastructure\Player\DiscordOAuthClient;
use App\Tests\Domain\Logging\SpyLogger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class DiscordOAuthClientTest extends TestCase
{
    private const string REDIRECT_URI = 'http://localhost:3000/auth/discord/callback';

    /** @var array<int, array{method: string, url: string, options: array<string, mixed>}> */
    private array $requests = [];

    private SpyLogger $logger;

    protected function setUp(): void
    {
        $this->logger = new SpyLogger();
    }

    /** @param MockResponse[] $responses */
    private function client(array $responses, string $clientId = 'client-id', string $clientSecret = 'secret'): DiscordOAuthClient
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$responses): MockResponse {
            $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return array_shift($responses) ?? throw new \LogicException('Requête Discord inattendue.');
        });

        return new DiscordOAuthClient($httpClient, $clientId, $clientSecret, self::REDIRECT_URI, $this->logger);
    }

    private static function json(array $body, int $status = 200): MockResponse
    {
        return new MockResponse(json_encode($body), ['http_code' => $status, 'response_headers' => ['content-type' => 'application/json']]);
    }

    public function testUnCodeValideDonneLIdentiteDuJoueur(): void
    {
        $identity = $this->client([
            self::json(['access_token' => 'jeton-discord', 'token_type' => 'Bearer']),
            self::json(['id' => '80351110224678912', 'username' => 'pseudo', 'global_name' => 'Pseudo Affiché']),
        ])->identityFromCode('code-du-front');

        $this->assertSame('80351110224678912', $identity->userId);
        $this->assertSame('pseudo', $identity->username);
    }

    public function testLEchangeDuCodeSuitLeProtocoleOAuthDeDiscord(): void
    {
        $this->client([
            self::json(['access_token' => 'jeton-discord']),
            self::json(['id' => '123', 'username' => 'pseudo']),
        ])->identityFromCode('code-du-front');

        [$tokenRequest, $identityRequest] = $this->requests;

        $this->assertSame('POST', $tokenRequest['method']);
        $this->assertSame('https://discord.com/api/v10/oauth2/token', $tokenRequest['url']);
        parse_str($tokenRequest['options']['body'], $form);
        $this->assertSame(
            ['grant_type' => 'authorization_code', 'code' => 'code-du-front', 'redirect_uri' => self::REDIRECT_URI],
            $form,
        );
        // Le secret part en authentification HTTP Basic, jamais dans l'URL.
        $this->assertContains('Authorization: Basic ' . base64_encode('client-id:secret'), $tokenRequest['options']['headers']);

        $this->assertSame('GET', $identityRequest['method']);
        $this->assertSame('https://discord.com/api/v10/users/@me', $identityRequest['url']);
        $this->assertContains('Authorization: Bearer jeton-discord', $identityRequest['options']['headers']);
    }

    public function testUnCodeRefuseParDiscordEchoue(): void
    {
        $this->expectException(DiscordLoginFailedException::class);

        $this->client([self::json(['error' => 'invalid_grant'], 400)])->identityFromCode('code-expiré');
    }

    public function testLaRaisonDuRefusDeDiscordEstJournalisee(): void
    {
        // invalid_grant couvre aussi une redirect_uri différente de celle du
        // front : sans ce log, une mauvaise configuration est indiagnosticable.
        try {
            $this->client([self::json(['error' => 'invalid_grant'], 400)])->identityFromCode('code-expiré');
        } catch (DiscordLoginFailedException) {
        }

        $warnings = $this->logger->records('warning');
        $this->assertCount(1, $warnings);
        $this->assertSame(400, $warnings[0]['context']['status']);
        $this->assertSame('invalid_grant', $warnings[0]['context']['error']);
    }

    public function testDiscordInjoignableEchoueProprement(): void
    {
        $this->expectException(DiscordLoginFailedException::class);

        $this->client([new MockResponse('', ['error' => 'Could not resolve host: discord.com'])])->identityFromCode('code');
    }

    public function testUneReponseSansIdentiteEchoue(): void
    {
        $this->expectException(DiscordLoginFailedException::class);

        $this->client([
            self::json(['access_token' => 'jeton-discord']),
            self::json(['username' => 'pseudo']),
        ])->identityFromCode('code');
    }

    public function testSansConfigurationAucuneRequeteNEstEnvoyee(): void
    {
        try {
            $this->client([], clientId: '', clientSecret: '')->identityFromCode('code');
            $this->fail('Une connexion Discord non configurée doit échouer.');
        } catch (DiscordLoginFailedException) {
            $this->assertSame([], $this->requests);
        }
    }
}
