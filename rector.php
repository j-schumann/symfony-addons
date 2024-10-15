<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitSelfCallRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Symfony\Set\SymfonySetList;
use Rector\Transform\Rector\Attribute\AttributeKeyToClassConstFetchRector;
use Rector\TypeDeclaration\Rector\ArrowFunction\AddArrowFunctionReturnTypeRector;

// @see https://getrector.com/blog/5-common-mistakes-in-rector-config-and-how-to-avoid-them
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ]);

    $rectorConfig->parallel(200, 4);

    // define sets of rules
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_84,
        SetList::CODE_QUALITY,
        SetList::TYPE_DECLARATION,

        // unwanted: changes if ($user) to if ($user instanceof \Symfony\Component\Security\Core\User\UserInterface)
        // SetList::INSTANCEOF,

        // unwanted: splits IF statements to force returns
        // SetList::EARLY_RETURN,

        // unwanted:
        // renames ChallengeConcretization $concretization to $challengeConcretization
        // renames $email = new TemplatedEmail() to $templatedEmail
        // SetList::NAMING,

        // verify changes, some are unwanted!
        // SetList::DEAD_CODE,

        DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES,
        DoctrineSetList::DOCTRINE_CODE_QUALITY,
        DoctrineSetList::DOCTRINE_DBAL_40,
        DoctrineSetList::DOCTRINE_ORM_214,
        DoctrineSetList::DOCTRINE_BUNDLE_210,
        DoctrineSetList::GEDMO_ANNOTATIONS_TO_ATTRIBUTES,

        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
        PHPUnitSetList::PHPUNIT_100,

        SymfonySetList::ANNOTATIONS_TO_ATTRIBUTES,
        SymfonySetList::SYMFONY_CODE_QUALITY,
        SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,
        SymfonySetList::SYMFONY_64,
    ]);

    $rectorConfig->rules([
        PreferPHPUnitSelfCallRector::class
    ]);

    $rectorConfig->skip([
        // mostly unnecessary as they are callbacks to array_filter etc.
        AddArrowFunctionReturnTypeRector::class,

        // replaces our (imported) Types::JSON with \Doctrine\DBAL\Types\Types::JSON
        AttributeKeyToClassConstFetchRector::class,

        // replaces null === $project with !$project instanceof Project
        FlipTypeControlToUseExclusiveTypeRector::class,

        // uses $this->assert... instead of self::assert
        // @see https://discourse.laminas.dev/t/this-assert-vs-self-assert/448
        PreferPHPUnitThisCallRector::class,

        // adds references with @see to the tests to the entity classes etc.
        //AddSeeTestAnnotationRector::class,
    ]);
};
