<?php

namespace App\Infrastructure\User;

use App\Domain\User\UserEmail;
use App\Domain\User\UserEntity;
use App\Domain\User\UserRepositoryInterface;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineUserRepository implements UserRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(UserEntity $user): void
    {
        $userDoctrine = new User();

        $userDoctrine->setEmail($user->getEmail()->getValue());
        $userDoctrine->setPassword($user->getPassword());
        $userDoctrine->setRoles($user->getRoles());

        $this->entityManager->persist($userDoctrine);
        $this->entityManager->flush();

    }

    public function findByEmail(UserEmail $email): ?UserEntity
    {
        $userDoctrine =  $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email->getValue()]);

        if ($userDoctrine === null) {
            return null;
        }

        return new UserEntity(
            new UserEmail($userDoctrine->getEmail()),
            $userDoctrine->getPassword(),
            $userDoctrine->getRoles()
        );
    }
}