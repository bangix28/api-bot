<?php

namespace App\Infrastructure\User;

use App\Domain\User\PasswordHasherInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface as SymfonyHasher;

readonly class SymfonyPasswordHasher implements PasswordHasherInterface
{

    public function __construct(private SymfonyHasher $symfonyHasher)
    {
    }

    public function hash(string $password): string
    {
        return $this->symfonyHasher->hash($password);
    }
}