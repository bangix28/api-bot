<?php

namespace App\Domain\User;

class UserEmail
{
    public function __construct(private string $value)
    {
        $this->validateEmail();
    }

    private function validateEmail(): void
    {
        if (!filter_var($this->value, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email format');
        }
    }

    public function getValue(): string
    {
        return $this->value;
    }
}