<?php

namespace App\Tests\Domain\User;

use App\Domain\User\PasswordHasherInterface;

class FakePasswordHasher implements PasswordHasherInterface
{

    public function hash(string $password): string
    {
        return 'hashed_'. $password;
    }
}