<?php

namespace App\Domain\User;

interface PasswordHasherInterface
{
    public function hash(string $password): string;
}