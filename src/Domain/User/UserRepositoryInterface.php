<?php

namespace App\Domain\User;

interface UserRepositoryInterface
{
    public function save(UserEntity $user): void;

    public function findByEmail(UserEmail $email): ?UserEntity;
}