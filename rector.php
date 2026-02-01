<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitSelfCallRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Symfony\Symfony73\Rector\Class_\InvokableCommandInputAttributeRector;
use Rector\Transform\Rector\Attribute\AttributeKeyToClassConstFetchRector;
use Rector\TypeDeclaration\Rector\ArrowFunction\AddArrowFunctionReturnTypeRector;

// @see https://getrector.com/blog/5-common-mistakes-in-rector-config-and-how-to-avoid-them
return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withParallel(200, 4)
    ->withComposerBased(
        twig: true,
        doctrine: true,
        phpunit: true,
        symfony: true,
    )
    ->withPreparedSets(
        // verify changes, some are unwanted!
        deadCode: false,
        codeQuality: true,
        typeDeclarations: true,
        typeDeclarationDocblocks: true,
        privatization: true,
        // unwanted:
        // renames ChallengeConcretization $concretization to $challengeConcretization
        // renames $email = new TemplatedEmail() to $templatedEmail
        naming: false,
        // unwanted: changes if ($user) to if ($user instanceof \Symfony\Component\Security\Core\User\UserInterface)
        instanceOf: false,
        // unwanted: splits IF statements to force returns
        earlyReturn: false,
        rectorPreset: true,
    )
    ->withPhpSets(php85: true)
    ->withSets([
        DoctrineSetList::DOCTRINE_CODE_QUALITY,
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
        PHPUnitSetList::PHPUNIT_120,
    ])
    ->withRules([
        PreferPHPUnitSelfCallRector::class,
    ])
    ->withSkip([
        __DIR__ . '/tests/Fixtures/app',

        // mostly unnecessary as they are callbacks to array_filter etc.
        AddArrowFunctionReturnTypeRector::class,

        // replaces our (imported) Types::JSON with \Doctrine\DBAL\Types\Types::JSON
        AttributeKeyToClassConstFetchRector::class,

        // replaces null === $project with !$project instanceof Project
        FlipTypeControlToUseExclusiveTypeRector::class,

        // uses $this->assert... instead of self::assert
        // @see https://discourse.laminas.dev/t/this-assert-vs-self-assert/448
        PreferPHPUnitThisCallRector::class,
    ])
;