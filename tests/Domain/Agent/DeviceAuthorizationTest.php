<?php

namespace App\Tests\Domain\Agent;

use App\Domain\Agent\DeviceAuthorization;
use App\Domain\Agent\DevicePollStatus;
use App\Domain\Agent\InvalidDeviceCodeException;
use App\Domain\Agent\InvalidUserCodeException;
use App\Domain\Agent\SecretDigest;
use App\Domain\Agent\UserCode;
use App\Domain\Player\DiscordIdentity;
use PHPUnit\Framework\TestCase;

class DeviceAuthorizationTest extends TestCase
{
    private const string REQUESTED_AT = '2026-11-07 20:00:00';

    private static function at(string $offset): \DateTimeImmutable
    {
        return (new \DateTimeImmutable(self::REQUESTED_AT))->modify($offset);
    }

    private static function requested(): DeviceAuthorization
    {
        return DeviceAuthorization::request('dev_secret', new UserCode('K7QF92PZ'), self::at('+0 seconds'));
    }

    private static function player(): DiscordIdentity
    {
        return new DiscordIdentity('1001', 'alpha');
    }

    public function testSeuleLEmpreinteDuDeviceCodeEstConservee(): void
    {
        $authorization = self::requested();

        $this->assertSame(SecretDigest::of('dev_secret'), $authorization->deviceCodeDigest);
        $this->assertNotSame('dev_secret', $authorization->deviceCodeDigest);
    }

    public function testLeCodeVautDixMinutes(): void
    {
        $authorization = self::requested();

        $this->assertFalse($authorization->isExpiredAt(self::at('+600 seconds')));
        $this->assertTrue($authorization->isExpiredAt(self::at('+601 seconds')));
    }

    public function testApprouverLieLAgentAuJoueurEtASaLan(): void
    {
        $approved = self::requested()->approve(self::player(), 3, self::at('+60 seconds'));

        $this->assertSame('1001', $approved->approval->player->userId);
        $this->assertSame(3, $approved->approval->lanId);
    }

    public function testUnCodeExpireNePeutPlusEtreApprouve(): void
    {
        $this->expectException(InvalidUserCodeException::class);

        self::requested()->approve(self::player(), 3, self::at('+601 seconds'));
    }

    public function testUnCodeNeSApprouveQuUneFois(): void
    {
        // Sans quoi un second joueur pourrait s'approprier l'agent d'un autre.
        $approved = self::requested()->approve(self::player(), 3, self::at('+60 seconds'));

        $this->expectException(InvalidUserCodeException::class);

        $approved->approve(new DiscordIdentity('1002', 'bravo'), 3, self::at('+90 seconds'));
    }

    public function testTantQueLeJoueurNAPasSaisiLeCodeLAgentPatiente(): void
    {
        $outcome = self::requested()->poll(self::at('+5 seconds'));

        $this->assertSame(DevicePollStatus::PENDING, $outcome->status);
        $this->assertEquals(self::at('+5 seconds'), $outcome->authorization->lastPolledAt);
    }

    public function testInterrogerPlusViteQueLIntervalleEstFreine(): void
    {
        $polledOnce = self::requested()->poll(self::at('+5 seconds'))->authorization;

        $this->assertSame(DevicePollStatus::SLOW_DOWN, $polledOnce->poll(self::at('+9 seconds'))->status);
        $this->assertSame(DevicePollStatus::PENDING, $polledOnce->poll(self::at('+10 seconds'))->status);
    }

    public function testUnCodeExpireSansApprobationEstSignale(): void
    {
        $this->assertSame(DevicePollStatus::EXPIRED, self::requested()->poll(self::at('+601 seconds'))->status);
    }

    public function testUnCodeApprouveEstPretAEtreEchange(): void
    {
        $approved = self::requested()->approve(self::player(), 3, self::at('+60 seconds'));

        $this->assertSame(DevicePollStatus::APPROVED, $approved->poll(self::at('+62 seconds'))->status);
    }

    public function testUnCodeEchangeNeResertPas(): void
    {
        $consumed = self::requested()->approve(self::player(), 3, self::at('+60 seconds'))->consume();

        $this->expectException(InvalidDeviceCodeException::class);

        $consumed->poll(self::at('+70 seconds'));
    }

    public function testUnCodeEchangeNePeutPlusEtreApprouve(): void
    {
        $consumed = self::requested()->approve(self::player(), 3, self::at('+60 seconds'))->consume();

        $this->expectException(InvalidUserCodeException::class);

        $consumed->approve(self::player(), 3, self::at('+70 seconds'));
    }

    public function testUnCodeNonApprouveNeSEchangePas(): void
    {
        $this->expectException(\LogicException::class);

        self::requested()->consume();
    }
}
