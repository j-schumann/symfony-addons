<?php

use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\CodingStyle\Rector\ClassLike\NewlineBetweenClassLikeStmtsRector;
use Rector\CodingStyle\Rector\Encapsed\EncapsedStringsToSprintfRector;
use Rector\CodingStyle\Rector\Encapsed\WrapEncapsedVariableInCurlyBracesRector;
use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Php74\Rector\Ternary\ParenthesizeNestedTernaryRector;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Php80\Rector\FuncCall\ClassOnObjectRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\AddSeeTestAnnotationRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitSelfCallRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector;
use Rector\PHPUnit\CodeQuality\Rector\StmtsAwareInterface\DeclareStrictTypesTestsRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Transform\Rector\Attribute\AttributeKeyToClassConstFetchRector;
use Rector\TypeDeclaration\Rector\ArrowFunction\AddArrowFunctionReturnTypeRector;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\DeclareStrictTypesRector;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\SafeDeclareStrictTypesRector;

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
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
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
        phpunitCodeQuality: true,
        doctrineCodeQuality: true,
        symfonyCodeQuality: true,
        symfonyConfigs: true,
    )
    ->withPhpSets(php85: true)
    ->withSets([
        DoctrineSetList::DOCTRINE_CODE_QUALITY,
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
    ])
    ->withRules([
        PreferPHPUnitSelfCallRector::class,
    ])
    ->withSkip([
        __DIR__.'/tests/Fixtures/app',

        // mostly unnecessary as they are callbacks to array_filter etc.
        AddArrowFunctionReturnTypeRector::class,

        // Adds `@see <TestClass>` docblocks to source classes — unwanted annotation noise.
        AddSeeTestAnnotationRector::class,

        // replaces our (imported) Types::JSON with \Doctrine\DBAL\Types\Types::JSON
        AttributeKeyToClassConstFetchRector::class,

        // Fires on ::class on object — often intentional (e.g. attribute key lookups).
        ClassOnObjectRector::class,

        // Entity convention: ORM-attributed properties must NOT use constructor promotion —
        // attributes must live on the class-body property declaration, not on ctor params.
        ClassPropertyAssignToConstructorPromotionRector::class,

        // unnecessary sprintf calls
        EncapsedStringsToSprintfRector::class,

        // replaces null === $project with !$project instanceof Project
        FlipTypeControlToUseExclusiveTypeRector::class,

        // adds a newline before our "// endregion" comments
        NewlineBetweenClassLikeStmtsRector::class,

        // Parenthesising nested ternaries is handled by cs-fixer; rector rewrite is redundant.
        ParenthesizeNestedTernaryRector::class,

        // uses $this->assert... instead of self::assert
        // @see https://discourse.laminas.dev/t/this-assert-vs-self-assert/448
        PreferPHPUnitThisCallRector::class,

        // adds unnecessary braces, would be removed again by cs-fixer
        WrapEncapsedVariableInCurlyBracesRector::class,

        // explicitly removed by @Symfony:risky with php-cs-fixer
        // @see https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/discussions/8877#discussioncomment-14776674
        DeclareStrictTypesRector::class,
        DeclareStrictTypesTestsRector::class,
        SafeDeclareStrictTypesRector::class,

        // Auto-generated, config reference dump
        __DIR__.'/tests/Fixtures/app/config/reference.php',
    ])
;
