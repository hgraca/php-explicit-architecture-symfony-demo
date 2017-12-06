<?php

declare(strict_types=1);

/*
 * This file is part of the Explicit Architecture POC,
 * which is created on top of the Symfony Demo application.
 *
 * (c) Herberto Graça <herberto.graca@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Acme\App\Core\Component\User\Application\Service;

use Acme\App\Core\Component\User\Application\Repository\Doctrine\UserRepository;
use Acme\App\Core\Component\User\Application\Validation\UserValidationService;
use Acme\App\Core\Component\User\Domain\Entity\User;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

final class UserService
{
    /**
     * @var UserPasswordEncoderInterface
     */
    private $encoder;

    /**
     * @var UserValidationService
     */
    private $validator;

    /**
     * @var UserRepository
     */
    private $userRepository;

    public function __construct(
        UserPasswordEncoderInterface $encoder,
        UserValidationService $validator,
        UserRepository $userRepository
    ) {
        $this->encoder = $encoder;
        $this->validator = $validator;
        $this->userRepository = $userRepository;
    }

    public function createUser(
        string $username,
        string $plainPassword,
        string $email,
        string $fullName,
        bool $isAdmin
    ): User {
        // make sure to validate the user data is correct
        $this->validateUserData($username, $plainPassword, $email, $fullName);

        // See https://symfony.com/doc/current/book/security.html#security-encoding-password
        // create the user and encode its password
        $user = User::constructWithoutPassword(
            $username,
            $email,
            $fullName,
            $isAdmin ? User::ROLE_ADMIN : User::ROLE_USER
        );
        $user->setPassword($this->encoder->encodePassword($user, $plainPassword));
        $this->userRepository->persist($user);

        return $user;
    }

    private function validateUserData($username, $plainPassword, $email, $fullName): void
    {
        // first check if a user with the same username already exists.
        $existingUser = $this->userRepository->findOneBy(['username' => $username]);

        if ($existingUser !== null) {
            throw new RuntimeException(
                sprintf('There is already a user registered with the "%s" username.', $username)
            );
        }

        // validate password and email if is not this input means interactive.
        $this->validator->validatePassword($plainPassword);
        $this->validator->validateEmail($email);
        $this->validator->validateFullName($fullName);

        // check if a user with the same email already exists.
        $existingEmail = $this->userRepository->findOneBy(['email' => $email]);

        if ($existingEmail !== null) {
            throw new RuntimeException(sprintf('There is already a user registered with the "%s" email.', $email));
        }
    }
}
