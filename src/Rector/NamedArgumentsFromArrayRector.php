<?php

namespace Vrok\SymfonyAddons\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PHPStan\Type\ObjectType;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class NamedArgumentsFromArrayRector extends AbstractRector implements ConfigurableRectorInterface
{
    /**
     * @var array<string|array{string, string}>
     */
    private array $targets = [];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert associative array arguments to named arguments for specified functions/methods',
            [
                new ConfiguredCodeSample(
                    <<<'CODE_SAMPLE'
                        foo([
                            'a' => $a,
                            'b' => $b,
                        ]);

                        MyClass::staticMethod([
                            'x' => $x,
                            'y' => $y,
                        ]);

                        $obj->instanceMethod([
                            'p' => $p,
                            'q' => $q,
                        ]);
                        CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
                        foo(a: $a, b: $b);

                        MyClass::staticMethod(x: $x, y: $y);

                        $obj->instanceMethod(p: $p, q: $q);
                        CODE_SAMPLE
                    ,
                    [
                        'targets' => [
                            'foo',  // function call
                            ['MyClass', 'staticMethod'],  // static method call
                            ['MyClass', 'instanceMethod'],  // instance method call
                        ],
                    ]
                ),
            ]
        );
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [FuncCall::class, MethodCall::class, StaticCall::class];
    }

    /**
     * @param FuncCall|MethodCall|StaticCall $node
     */
    public function refactor(Node $node): ?Node
    {
        if (!$this->shouldProcessNode($node)) {
            return null;
        }

        if (1 !== \count($node->args)) {
            return null;
        }

        $firstArg = $node->args[0];
        if (
            !$firstArg instanceof Arg
            || null !== $firstArg->name
            || !$firstArg->value instanceof Array_
        ) {
            return null;
        }

        $namedArgs = $this->convertArrayItemsToNamedArgs($firstArg->value);
        if (null === $namedArgs || [] === $namedArgs) {
            return null;
        }

        $node->args = $namedArgs;

        return $node;
    }

    /**
     * @param mixed[] $configuration
     */
    public function configure(array $configuration): void
    {
        $this->targets = $configuration['targets'] ?? [];
    }

    private function shouldProcessNode(Node $node): bool
    {
        if ($node instanceof FuncCall) {
            return $this->isFunctionCallTarget($node);
        }

        if ($node instanceof MethodCall) {
            return $this->isClassMethodTarget($node->name, $node->var);
        }

        if ($node instanceof StaticCall) {
            return $this->isClassMethodTarget($node->name, $node->class);
        }

        return false;
    }

    private function isFunctionCallTarget(FuncCall $funcCall): bool
    {
        $functionName = $this->getName($funcCall);
        if (null === $functionName) {
            return false;
        }

        // string targets are function calls
        return array_any(
            $this->targets,
            static fn (array|string $target): bool => \is_string($target)
                && 0 === strcasecmp($target, $functionName),
        );
    }

    /**
     * @param Node $name   the method name, an Identifier unless the call is dynamic
     * @param Node $object the variable of an instance call or the class of a static call
     */
    private function isClassMethodTarget(Node $name, Node $object): bool
    {
        if (!$name instanceof Identifier) {
            return false;
        }

        $methodName = $name->toString();

        foreach ($this->targets as $target) {
            if (\is_array($target)) {
                [$targetClass, $targetMethod] = $target;

                if (
                    0 === strcasecmp($targetMethod, $methodName)
                    && $this->isObjectType($object, new ObjectType($targetClass))
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return Arg[]|null null if not every item has a string key
     */
    private function convertArrayItemsToNamedArgs(Array_ $array): ?array
    {
        $namedArgs = [];

        foreach ($array->items as $item) {
            if (!$item->key instanceof String_) {
                return null;
            }

            $namedArgs[] = new Arg($item->value, name: new Identifier($item->key->value));
        }

        return $namedArgs;
    }
}
