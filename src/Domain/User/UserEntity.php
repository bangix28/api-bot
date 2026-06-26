<?php

namespace App\Domain\User;

class UserEntity
{
    public function __construct(
        private UserEmail $email,
        private string    $password,
        private array     $rolesUser
    )
    {
        $this->validateUser();
    }

    public function validateUser(): void
    {

        if (empty($this->password)) {
            throw new \InvalidArgumentException('Password cannot be empty');
        }

        if (count($this->rolesUser) === 0) {
            throw new \InvalidArgumentException('User must have at least one role');
        }
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function withPassword(string $password): self
    {
        return new static($this->email, $password, $this->rolesUser);
    }

    public function withRolesUser(array $rolesUser): self
    {
        return new static($this->email, $this->password, $rolesUser);
    }

    public function withEmail(UserEmail $email): UserEntity
    {
        return new static($email, $this->password, $this->rolesUser);
    }

    public function getRoles(): array
    {
        return $this->rolesUser;
    }

    public function hasRole(string $role): bool
    {
        $key = array_search($role, $this->rolesUser);
        return $key !== false;
    }

    public function getEmail(): UserEmail
    {
        return $this->email;
    }


}