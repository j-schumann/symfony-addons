<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
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

    public function refactor(Node $node): ?Node
    {
        if (!$this->shouldProcessNode($node)) {
            return null;
        }

        // Check if there's exactly one argument and it's an array
        if (count($node->args) !== 1) {
            return null;
        }

        $firstArg = $node->args[0];
        if (!$firstArg instanceof Arg || !$firstArg->value instanceof Array_) {
            return null;
        }

        $array = $firstArg->value;

        // Check if all array items have string keys (associative array)
        if (!$this->isAssociativeArray($array)) {
            return null;
        }

        // Convert array items to named arguments
        $namedArgs = $this->convertArrayItemsToNamedArgs($array);

        if ($namedArgs === []) {
            return null;
        }

        $node->args = $namedArgs;

        return $node;
    }

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
            return $this->isMethodCallTarget($node);
        }

        if ($node instanceof StaticCall) {
            return $this->isStaticCallTarget($node);
        }

        return false;
    }

    private function isFunctionCallTarget(FuncCall $funcCall): bool
    {
        if (!$funcCall->name instanceof Identifier) {
            return false;
        }

        $functionName = $funcCall->name->toString();

        foreach ($this->targets as $target) {
            // String targets are function calls
            if (is_string($target) && $target === $functionName) {
                return true;
            }
        }

        return false;
    }

    private function isMethodCallTarget(MethodCall $methodCall): bool
    {
        if (!$methodCall->name instanceof Identifier) {
            return false;
        }

        $methodName = $methodCall->name->toString();

        foreach ($this->targets as $target) {
            if (is_array($target) && count($target) === 2) {
                [, $targetMethod] = $target; // We can't reliably match class for instance methods

                // For instance method calls, we can only match by method name
                // since we can't determine the exact class of $obj in $obj->method() at compile time
                if ($targetMethod === $methodName) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isStaticCallTarget(StaticCall $staticCall): bool
    {
        if (!$staticCall->name instanceof Identifier) {
            return false;
        }

        $methodName = $staticCall->name->toString();

        // Get class name
        $className = null;
        if ($staticCall->class instanceof Identifier) {
            $className = $staticCall->class->toString();
        }

        foreach ($this->targets as $target) {
            if (is_array($target) && count($target) === 2) {
                [$targetClass, $targetMethod] = $target;

                // For static calls, we can match both class and method exactly
                if ($targetClass === $className && $targetMethod === $methodName) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isAssociativeArray(Array_ $array): bool
    {
        foreach ($array->items as $item) {
            if (!$item instanceof ArrayItem) {
                continue;
            }

            // If key is null, it's a numeric array
            if ($item->key === null) {
                return false;
            }

            // We only support string keys for named arguments
            if (!$item->key instanceof String_) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return Arg[]
     */
    private function convertArrayItemsToNamedArgs(Array_ $array): array
    {
        $namedArgs = [];

        foreach ($array->items as $item) {
            if (!$item instanceof ArrayItem || !$item->key instanceof String_) {
                continue;
            }

            $paramName = $item->key->value;
            $namedArgs[] = new Arg(
                $item->value,
                false, // byRef
                false, // unpack
                [],    // attributes
                new Identifier($paramName) // name
            );
        }

        return $namedArgs;
    }
}
