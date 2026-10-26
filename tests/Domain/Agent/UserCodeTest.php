<?php

namespace App\Tests\Domain\Agent;

use App\Domain\Agent\InvalidUserCodeException;
use App\Domain\Agent\UserCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UserCodeTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function typings(): iterable
    {
        yield 'tel qu\'affiché' => ['K7QF-92PZ'];
        yield 'en minuscules' => ['k7qf-92pz'];
        yield 'sans tiret' => ['k7qf92pz'];
        yield 'avec des espaces' => ['  K7QF 92PZ '];
    }

    #[DataProvider('typings')]
    public function testLaSaisieEstToleree(string $typed): void
    {
        $userCode = new UserCode($typed);

        $this->assertSame('K7QF92PZ', $userCode->value);
        $this->assertSame('K7QF-92PZ', $userCode->display());
    }

    /** @return iterable<string, array{string}> */
    public static function malformed(): iterable
    {
        yield 'trop court' => ['K7QF-92P'];
        yield 'trop long' => ['K7QF-92PZA'];
        yield 'zéro ambigu' => ['K7QF-920Z'];
        yield 'O ambigu' => ['K7QF-92OZ'];
        yield 'un ambigu' => ['K7QF-921Z'];
        yield 'I ambigu' => ['K7QF-92IZ'];
        yield 'caractère spécial' => ['K7QF-92P!'];
        yield 'huit bons caractères puis un intrus' => ['K7QF-92PZ!'];
        yield 'vide' => [''];
    }

    #[DataProvider('malformed')]
    public function testUnCodeMalFormeEstRefuse(string $typed): void
    {
        $this->expectException(InvalidUserCodeException::class);

        new UserCode($typed);
    }
}
