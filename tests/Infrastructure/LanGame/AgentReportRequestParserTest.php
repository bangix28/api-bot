<?php

namespace App\Tests\Infrastructure\LanGame;

use App\Domain\Agent\AgentToken;
use App\Domain\LanGame\InvalidReportException;
use App\Domain\Player\DiscordIdentity;
use App\Infrastructure\LanGame\AgentReportRequestParser;
use App\Infrastructure\LanGame\ReportTooLargeException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AgentReportRequestParserTest extends TestCase
{
    private static function token(): AgentToken
    {
        return new AgentToken('digest', new DiscordIdentity('1001', 'alpha'), 7, new \DateTimeImmutable('2026-11-08 04:00'));
    }

    /** @return array<string, mixed> */
    private static function envelope(array $overrides = []): array
    {
        return [
            'agentVersion' => '1.0.0',
            'sessionId' => '8D0E5A7C-2F1B-4C3E-9A6D-1B2C3D4E5F60',
            'sequence' => 17,
            'final' => true,
            'endReason' => 'game_end',
            'observedAt' => '2026-11-07T21:30:21+01:00',
            'liveClient' => LiveClientSnapshotParserTest::realGame(),
            ...$overrides,
        ];
    }

    private static function json(array $envelope): string
    {
        return json_encode($envelope, JSON_THROW_ON_ERROR);
    }

    public function testLitLEnveloppe(): void
    {
        $body = self::json(self::envelope());

        $command = AgentReportRequestParser::parse(self::token(), $body, null);

        $this->assertSame(self::token()->digest, $command->token->digest);
        $this->assertSame('8d0e5a7c-2f1b-4c3e-9a6d-1b2c3d4e5f60', $command->sessionId, 'En minuscules : un rejeu retombe sur la même session.');
        $this->assertSame(17, $command->sequence);
        $this->assertTrue($command->final);
        $this->assertSame('Joueur3#EUW', $command->snapshot->activeRiotId);
        $this->assertSame($body, $command->rawPayload);
    }

    public function testLaFileLueDansLeClientLoLAccompagneLeReleve(): void
    {
        $command = AgentReportRequestParser::parse(self::token(), self::json(self::envelope(['queueId' => 2400])), null);

        $this->assertSame(2400, $command->snapshot->queueId);
    }

    public function testSansFileLueLeReleveEstQuandMemeAccepte(): void
    {
        $this->assertNull(AgentReportRequestParser::parse(self::token(), self::json(self::envelope()), null)->snapshot->queueId, 'Champ absent.');
        $this->assertNull(AgentReportRequestParser::parse(self::token(), self::json(self::envelope(['queueId' => null])), null)->snapshot->queueId, 'Champ null.');
    }

    public function testUnReleveIntermediaire(): void
    {
        $command = AgentReportRequestParser::parse(self::token(), self::json(self::envelope(['final' => false, 'endReason' => null, 'observedAt' => '2026-11-07T20:30:21.123Z'])), 'identity');

        $this->assertFalse($command->final);
    }

    public function testDecompresseUnCorpsGzip(): void
    {
        $body = self::json(self::envelope());

        $command = AgentReportRequestParser::parse(self::token(), (string) gzencode($body), 'GZIP');

        $this->assertSame($body, $command->rawPayload);
    }

    public function testUnGzipCorrompuEstRefuse(): void
    {
        $this->expectException(InvalidReportException::class);
        $this->expectExceptionMessage('Corps gzip corrompu.');

        AgentReportRequestParser::parse(self::token(), 'pas du gzip', 'gzip');
    }

    public function testUnEncodageInconnuEstRefuse(): void
    {
        $this->expectException(InvalidReportException::class);
        $this->expectExceptionMessage('Content-Encoding « br » non pris en charge');

        AgentReportRequestParser::parse(self::token(), self::json(self::envelope()), 'br');
    }

    public function testUnCorpsTropGrosEstRefuse(): void
    {
        $this->expectException(ReportTooLargeException::class);

        AgentReportRequestParser::parse(self::token(), str_repeat(' ', AgentReportRequestParser::MAX_BYTES + 1), null);
    }

    public function testUneBombeGzipEstArreteeALaLimite(): void
    {
        $this->expectException(ReportTooLargeException::class);

        AgentReportRequestParser::parse(self::token(), (string) gzencode(str_repeat(' ', 20 * AgentReportRequestParser::MAX_BYTES)), 'gzip');
    }

    public function testUnCorpsALaLimiteEstAccepte(): void
    {
        $body = self::json(self::envelope());
        $padded = $body . str_repeat(' ', AgentReportRequestParser::MAX_BYTES - strlen($body));

        $this->assertSame($padded, AgentReportRequestParser::parse(self::token(), (string) gzencode($padded), 'gzip')->rawPayload);
        $this->assertSame($padded, AgentReportRequestParser::parse(self::token(), $padded, null)->rawPayload);
    }

    /** @return iterable<string, array{string}> */
    public static function unreadable(): iterable
    {
        yield 'JSON invalide' => ['{"agentVersion":'];
        yield 'pas un objet' => ['"relevé"'];
        yield 'vide' => [''];
    }

    #[DataProvider('unreadable')]
    public function testUnCorpsIllisibleEstRefuse(string $body): void
    {
        $this->expectException(InvalidReportException::class);

        AgentReportRequestParser::parse(self::token(), $body, null);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidEnvelopes(): iterable
    {
        yield 'sans version' => [['agentVersion' => null]];
        yield 'version vide' => [['agentVersion' => '']];
        yield 'session qui n\'est pas un UUID' => [['sessionId' => 'session-1']];
        yield 'session suivie d\'autre chose' => [['sessionId' => '8d0e5a7c-2f1b-4c3e-9a6d-1b2c3d4e5f60x']];
        yield 'séquence nulle' => [['sequence' => 0]];
        yield 'séquence en texte' => [['sequence' => '17']];
        yield 'final absent' => [['final' => null]];
        yield 'final en texte' => [['final' => 'true']];
        yield 'date sans fuseau' => [['observedAt' => '2026-11-07T21:30:21']];
        yield 'date absente' => [['observedAt' => null]];
        yield 'relevé final sans raison' => [['endReason' => null]];
        yield 'relevé final avec une raison inconnue' => [['endReason' => 'rage_quit']];
        yield 'relevé intermédiaire avec une raison' => [['final' => false, 'endReason' => 'game_end']];
        yield 'file en texte' => [['queueId' => '420']];
        yield 'liveClient absent' => [['liveClient' => null]];
        yield 'liveClient incomplet' => [['liveClient' => ['gameData' => []]]];
    }

    /** @param array<string, mixed> $overrides */
    #[DataProvider('invalidEnvelopes')]
    public function testUneEnveloppeInvalideEstRefusee(array $overrides): void
    {
        $this->expectException(InvalidReportException::class);

        AgentReportRequestParser::parse(self::token(), self::json(self::envelope($overrides)), null);
    }

    public function testLeReleveFinalAccepteLaFermetureDuClient(): void
    {
        $this->assertTrue(AgentReportRequestParser::parse(self::token(), self::json(self::envelope(['endReason' => 'client_closed'])), null)->final);
    }
}
