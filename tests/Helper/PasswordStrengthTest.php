<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\Helper;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vrok\SymfonyAddons\Helper\PasswordStrength;

class PasswordStrengthTest extends TestCase
{
    public static function getValues(): array
    {
        return [
            ['', -6.0],
            ['11112222', 7.9375],
            ['1234567890', 16.0],
            ['password', 17.5],
            ['p4ssw0rd', 18.5],
            ['PassWord', 19.5],
            ['pa$$word', 19.5],
            ['P4$$w0rd', 22.5],
            ['longerP4$$w0rd', 31.25],
            ['only some irrelevant words', 39.40625],
        ];
    }

    #[DataProvider('getValues')]
    public function testGetStrength($value, $strength): void
    {
        $pwStrength = new PasswordStrength();
        $calculated = $pwStrength->getStrength($value);

        self::assertSame($strength, $calculated);
    }

    public function testGetThresholds(): void
    {
        $pwStrength = new PasswordStrength();
        $defaults = [
            PasswordStrength::RATING_WEAK  => 15,
            PasswordStrength::RATING_OK    => 20,
            PasswordStrength::RATING_GOOD  => 25,
            PasswordStrength::RATING_GREAT => 30,
        ];

        self::assertSame($defaults, $pwStrength->getThresholds());
    }

    public function testSetThresholds(): void
    {
        $pwStrength = new PasswordStrength();
        $pwStrength->setThresholds([PasswordStrength::RATING_GOOD => 28]);

        $modified = [
            PasswordStrength::RATING_WEAK  => 15,
            PasswordStrength::RATING_OK    => 20,
            PasswordStrength::RATING_GOOD  => 28,
            PasswordStrength::RATING_GREAT => 30,
        ];

        self::assertSame($modified, $pwStrength->getThresholds());
    }

    public function testGetRating(): void
    {
        $pwStrength = new PasswordStrength();
        self::assertSame(PasswordStrength::RATING_WEAK, $pwStrength->getRating(15));
        self::assertSame(PasswordStrength::RATING_OK, $pwStrength->getRating(20));
        self::assertSame(PasswordStrength::RATING_GOOD, $pwStrength->getRating(25));
        self::assertSame(PasswordStrength::RATING_GREAT, $pwStrength->getRating(30));
    }

    public function testRatePassword(): void
    {
        $pwStrength = new PasswordStrength();
        self::assertSame(PasswordStrength::RATING_BAD, $pwStrength->ratePassword('123'));
        self::assertSame(PasswordStrength::RATING_WEAK, $pwStrength->ratePassword('123asd'));
        self::assertSame(PasswordStrength::RATING_OK, $pwStrength->ratePassword('myPassword'));
        self::assertSame(PasswordStrength::RATING_GOOD, $pwStrength->ratePassword('myL0ngPassw'));
        self::assertSame(PasswordStrength::RATING_GREAT, $pwStrength->ratePassword('my very l0ng Pa$$word'));
    }
}
