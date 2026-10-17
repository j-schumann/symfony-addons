<?php

namespace Vrok\SymfonyAddons\Tests\Filter;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Parameter;
use ApiPlatform\Metadata\QueryParameter;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Vrok\SymfonyAddons\Filter\ParameterFilterTrait;

final class ParameterFilterTraitTest extends TestCase
{
    public function testApply(): void
    {
        $filter = $this->createFilter();
        $parameter = new QueryParameter(key: 'q');

        $filter->apply(
            $this->createStub(QueryBuilder::class),
            $this->createStub(QueryNameGeneratorInterface::class),
            'App\Entity\Test',
            null,
            ['parameter' => $parameter],
        );

        self::assertSame($parameter, $filter->parameter);
    }

    public function testApplyFailsWithoutParameter(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('supports only #[QueryParameter], not #[ApiFilter]');

        $this->createFilter()->apply(
            $this->createStub(QueryBuilder::class),
            $this->createStub(QueryNameGeneratorInterface::class),
            'App\Entity\Test',
        );
    }

    private function createFilter(): object
    {
        return new class {
            use ParameterFilterTrait;

            public ?Parameter $parameter = null;

            protected function filterParameter(
                Parameter $parameter,
                QueryBuilder $queryBuilder,
                QueryNameGeneratorInterface $queryNameGenerator,
                string $resourceClass,
                ?Operation $operation = null,
                array $context = [],
            ): void {
                $this->parameter = $parameter;
            }
        };
    }
}
