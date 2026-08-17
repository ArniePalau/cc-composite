<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Tests\Form;

use ArniePalau\CcComposite\Form\DTO\AdminNewUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class AdminNewUserTest extends TestCase
{
    public function testAcceptsThreeCharacterUsername(): void
    {
        $account = new AdminNewUser();
        $account->setUsername('Via');
        $account->setEmail('via@example.test');
        $account->setPassword('securepass123');

        $errors = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($account);

        self::assertCount(0, $errors);
    }

    public function testRejectsTwoCharacterUsername(): void
    {
        $account = new AdminNewUser();
        $account->setUsername('Vi');
        $account->setEmail('via@example.test');
        $account->setPassword('securepass123');

        $errors = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($account);

        self::assertGreaterThan(0, count($errors));
    }
}
