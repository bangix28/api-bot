<?php

namespace App\Tests\Domain\User;

use App\Domain\User\UserEmail;
use App\Domain\User\UserEntity;
use App\Domain\User\UserRepositoryInterface;

class InMemoryUserRepository implements UserRepositoryInterface
{
    private array $users = [];

    public function __construct()
    {
    }

    public function save(UserEntity $user): void
    {
        $this->users[$user->getEmail()->getValue()] = $user;
    }

    public function findByEmail(UserEmail $email): ?UserEntity
    {
        return $this->users[$email->getValue()] ?? null;
    }
}