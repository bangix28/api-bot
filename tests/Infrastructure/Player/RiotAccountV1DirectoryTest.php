<?php

namespace App\Tests\Infrastructure\Player;

use App\Domain\Player\RiotIdLookupFailedException;
use App\Infrastructure\Player\RiotAccountV1Directory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class RiotAccountV1DirectoryTest extends TestCase
{
    /** @var array<int, array{method: string, url: string, options: array<string, mixed>}> */
    private array $requests = [];

    private function directory(MockResponse $response): RiotAccountV1Directory
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use ($response): MockResponse {
            $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return $response;
        });

        return new RiotAccountV1Directory($httpClient, 'RGAPI-cle-de-test');
    }

    private static function json(array $body, int $status = 200): MockResponse
    {
        return new MockResponse(json_encode($body), ['http_code' => $status, 'response_headers' => ['content-type' => 'application/json']]);
    }

    public function testDonneLeRiotIdActuelDuCompte(): void
    {
        $riotId = $this->directory(self::json(['puuid' => 'puuid-a', 'gameName' => 'BCTG sHoTeUr', 'tagLine' => '4861']))
            ->currentRiotIdOf('puuid-a');

        $this->assertSame('BCTG sHoTeUr#4861', $riotId?->value);
    }

    public function testInterrogeAccountV1SurLeRoutageEuropeAvecLaCle(): void
    {
        $this->directory(self::json(['gameName' => 'Pseudo', 'tagLine' => 'EUW']))->currentRiotIdOf('Pn K8/s8?');

        $this->assertSame('GET', $this->requests[0]['method']);
        $this->assertSame('https://europe.api.riotgames.com/riot/account/v1/accounts/by-puuid/Pn%20K8%2Fs8%3F', $this->requests[0]['url']);
        $this->assertContains('X-Riot-Token: RGAPI-cle-de-test', $this->requests[0]['options']['headers']);
    }

    public function testUnPuuidInconnuDeRiotNeDonneRien(): void
    {
        $this->assertNull($this->directory(self::json(['status' => ['status_code' => 404]], 404))->currentRiotIdOf('puuid-inconnu'));
    }

    public function testUnRefusDeRiotDonneSonCodeDansLeMessage(): void
    {
        $this->expectException(RiotIdLookupFailedException::class);
        $this->expectExceptionMessage('Riot a répondu 429 pour ce compte.');

        $this->directory(self::json(['status' => ['status_code' => 429]], 429))->currentRiotIdOf('puuid-a');
    }

    public function testUnCompteSansTagEstSignaleCommeTel(): void
    {
        $this->expectException(RiotIdLookupFailedException::class);
        $this->expectExceptionMessage('Riot n\'a pas renvoyé de pseudo et de tag pour ce compte.');

        $this->directory(self::json(['gameName' => 'Pseudo']))->currentRiotIdOf('puuid-a');
    }

    /** @return iterable<string, array{MockResponse}> */
    public static function unusableAnswers(): iterable
    {
        yield 'clé expirée' => [self::json(['status' => ['status_code' => 403]], 403)];
        yield 'limite d\'appels' => [self::json(['status' => ['status_code' => 429]], 429)];
        yield 'panne chez Riot' => [self::json([], 503)];
        yield 'réponse illisible' => [new MockResponse('<html>', ['http_code' => 200])];
        yield 'sans tag' => [self::json(['gameName' => 'Pseudo'])];
        yield 'sans pseudo' => [self::json(['tagLine' => 'EUW'])];
        yield 'pseudo vide' => [self::json(['gameName' => '', 'tagLine' => 'EUW'])];
        yield 'Riot injoignable' => [new MockResponse([new TransportException('Connexion refusée')])];
    }

    #[DataProvider('unusableAnswers')]
    public function testUneReponseInexploitableEstUnEchec(MockResponse $response): void
    {
        $this->expectException(RiotIdLookupFailedException::class);

        $this->directory($response)->currentRiotIdOf('puuid-a');
    }
}
