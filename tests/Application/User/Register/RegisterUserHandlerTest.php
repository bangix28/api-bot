<?php

namespace App\Tests\Application\User\Register;

use App\Application\User\Register\RegisterUserCommand;
use App\Application\User\Register\RegisterUserHandler;
use App\Domain\User\UserAlreadyExistException;
use App\Domain\User\UserEmail;
use App\Domain\User\UserEntity;
use App\Tests\Domain\User\FakePasswordHasher;
use App\Tests\Domain\User\InMemoryUserRepository;
use PHPUnit\Framework\TestCase;

class RegisterUserHandlerTest extends TestCase
{

    public function testRegisterUserSuccessfully(): void
    {
        $repository = new InMemoryUserRepository();
        $hasher = new FakePasswordHasher();
        $handler =  new RegisterUserHandler($repository, $hasher);

        $handler->handle(new RegisterUserCommand('test@example.com', 'password'));

        $user = $repository->findByEmail(new UserEmail('test@example.com'));
        $this->assertNotNull($user);
        $this->assertEquals('hashed_password', $user->getPassword());
    }

    public function testRegisterUserWithExistingEmailThrowsException(): void
    {
        $repository = new InMemoryUserRepository();
        $hasher = new FakePasswordHasher();
        $handler =  new RegisterUserHandler($repository, $hasher);

        $repository->save(new UserEntity(new UserEmail('test@example.com'), 'hashed_pass', ['ROLE_USER']));

        $this->expectException(UserAlreadyExistException::class);
        $handler->handle(new RegisterUserCommand('test@example.com', 'password'));

    }

}