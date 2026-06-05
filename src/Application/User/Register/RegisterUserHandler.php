<?php

namespace App\Application\User\Register;

use App\Domain\User\PasswordHasherInterface;
use App\Domain\User\UserAlreadyExistException;
use App\Domain\User\UserEmail;
use App\Domain\User\UserEntity;
use App\Domain\User\UserRepositoryInterface;

class RegisterUserHandler
{
    public function __construct(private UserRepositoryInterface $userRepository,private PasswordHasherInterface $passwordHasher)
    {
    }

    public function handle(RegisterUserCommand $command): void
    {
        $userEmail = new UserEmail($command->email);

        if ($this->userRepository->findByEmail($userEmail)) {
            throw new UserAlreadyExistException();
        }

        $hashedPassword = $this->passwordHasher->hash($command->password);

        $userEntity = new UserEntity($userEmail, $hashedPassword, ["ROLE_USER"]);

        $this->userRepository->save($userEntity);
    }
}