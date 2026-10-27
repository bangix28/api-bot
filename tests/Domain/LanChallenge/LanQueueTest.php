<?php

namespace App\Tests\Domain\LanChallenge;

use App\Domain\LanChallenge\LanQueue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LanQueueTest extends TestCase
{
    /** @return iterable<string, array{?int, string, ?LanQueue}> */
    public static function observations(): iterable
    {
        yield 'ARAM du chaos lue dans le client' => [2400, 'KIWI', LanQueue::ARAM_MAYHEM];
        yield 'classée solo/duo' => [420, 'CLASSIC', LanQueue::RANKED_SOLO];
        yield 'classée flexible' => [440, 'CLASSIC', LanQueue::RANKED_FLEX];
        yield 'KIWI sans file lue : le mode suffit' => [null, 'KIWI', LanQueue::ARAM_MAYHEM];
        yield 'CLASSIC sans file lue : SoloQ, Flex, normale ou perso ?' => [null, 'CLASSIC', null];
        yield 'partie personnalisée' => [0, 'CLASSIC', null];
        yield 'normale' => [400, 'CLASSIC', null];
        yield 'ARAM classique' => [450, 'ARAM', null];
        yield 'file incohérente avec le mode' => [420, 'KIWI', null];
    }

    #[DataProvider('observations')]
    public function testLaFileDUnePartieObservee(?int $queueId, string $gameMode, ?LanQueue $expected): void
    {
        $this->assertSame($expected, LanQueue::observed($queueId, $gameMode));
    }

    public function testSeulesLesClasseesSeJouentSurLaFaille(): void
    {
        $this->assertFalse(LanQueue::ARAM_MAYHEM->playsOnRift());
        $this->assertTrue(LanQueue::RANKED_SOLO->playsOnRift());
        $this->assertTrue(LanQueue::RANKED_FLEX->playsOnRift());
    }
}
