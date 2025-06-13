<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\PhpCsFixer;

use PhpCsFixer\Fixer\ConfigurableFixerInterface;
use PhpCsFixer\Fixer\FixerInterface;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolver;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolverInterface;
use PhpCsFixer\FixerConfiguration\FixerOptionBuilder;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

final class WrapMethodArgumentsFixer implements FixerInterface, ConfigurableFixerInterface
{
    private array $configuration = [];

    public function getName(): string
    {
        return 'VrokSymfonyAddons/method_argument_wrap';
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Wrap method/function calls with many arguments to multiple lines.',
            [
                new CodeSample(
                    '<?php testFunction(param1: "value1", param2: "value2", param3: "value3", param4: "value4");'
                ),
                new CodeSample(
                    '<?php testFunction("value1", "value2", "value3", "value4");',
                    ['named_arguments_only' => false]
                ),
            ]
        );
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isTokenKindFound(T_STRING);
    }

    public function isRisky(): bool
    {
        return false;
    }

    public function fix(\SplFileInfo $file, Tokens $tokens): void
    {
        $maxArguments = $this->configuration['max_arguments'];
        $namedArgumentsOnly = $this->configuration['named_arguments_only'];

        for ($index = $tokens->count() - 1; $index >= 0; --$index) {
            if (!$tokens[$index]->equals('(')) {
                continue;
            }

            $openParenIndex = $index;
            $closeParenIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $openParenIndex);

            // Check if this is a function/method call
            if (!$this->isFunctionOrMethodCall($tokens, $openParenIndex)) {
                continue;
            }

            // Skip if already multiline
            if ($this->isAlreadyMultiline($tokens, $openParenIndex, $closeParenIndex)) {
                continue;
            }

            $arguments = $this->getArguments($tokens, $openParenIndex, $closeParenIndex);

            // Skip if no arguments
            if (empty($arguments)) {
                continue;
            }

            // Check if we should process this call
            $shouldProcess = count($arguments) > $maxArguments;

            if ($namedArgumentsOnly) {
                $shouldProcess = $shouldProcess && $this->hasNamedArguments($tokens, $arguments);
            }

            if ($shouldProcess) {
                $this->wrapArguments($tokens, $openParenIndex, $closeParenIndex, $arguments);
            }
        }
    }

    public function getPriority(): int
    {
        // Run after method_argument_space
        return -1;
    }

    public function supports(\SplFileInfo $file): bool
    {
        return true;
    }

    public function configure(array $configuration): void
    {
        $this->configuration = $this->getConfigurationDefinition()->resolve($configuration);
    }

    public function getConfigurationDefinition(): FixerConfigurationResolverInterface
    {
        return new FixerConfigurationResolver([
            (new FixerOptionBuilder('max_arguments', 'Maximum number of arguments before wrapping to multiple lines.'))
                ->setAllowedTypes(['int'])
                ->setDefault(3)
                ->getOption(),
            (new FixerOptionBuilder('named_arguments_only', 'Whether to only wrap calls that use named arguments.'))
                ->setAllowedTypes(['bool'])
                ->setDefault(true)
                ->getOption(),
        ]);
    }

    private function isFunctionOrMethodCall(Tokens $tokens, int $openParenIndex): bool
    {
        $prevIndex = $tokens->getPrevMeaningfulToken($openParenIndex);

        if (null === $prevIndex) {
            return false;
        }

        // Check for function call: functionName(
        if ($tokens[$prevIndex]->isGivenKind(T_STRING)) {
            return true;
        }

        // Check for method call: ->method( or ::method(
        if ($tokens[$prevIndex]->isGivenKind(T_STRING)) {
            $prevPrevIndex = $tokens->getPrevMeaningfulToken($prevIndex);
            if (null !== $prevPrevIndex
                && ($tokens[$prevPrevIndex]->isGivenKind(T_OBJECT_OPERATOR)
                    || $tokens[$prevPrevIndex]->isGivenKind(T_DOUBLE_COLON))) {
                return true;
            }
        }

        return false;
    }

    private function isAlreadyMultiline(Tokens $tokens, int $start, int $end): bool
    {
        for ($i = $start; $i <= $end; ++$i) {
            if ($tokens[$i]->isGivenKind(T_WHITESPACE) && str_contains($tokens[$i]->getContent(), "\n")) {
                return true;
            }
        }

        return false;
    }

    private function getArguments(Tokens $tokens, int $start, int $end): array
    {
        $arguments = [];
        $currentArg = [];
        $depth = 0;

        for ($i = $start + 1; $i < $end; ++$i) {
            $token = $tokens[$i];

            if ($token->equals('(') || $token->equals('[')) {
                ++$depth;
            } elseif ($token->equals(')') || $token->equals(']')) {
                --$depth;
            } elseif ($token->equals(',') && 0 === $depth) {
                if (!empty($currentArg)) {
                    $arguments[] = $this->trimArgument($tokens, $currentArg);
                    $currentArg = [];
                }
                continue;
            }

            $currentArg[] = $i;
        }

        if (!empty($currentArg)) {
            $arguments[] = $this->trimArgument($tokens, $currentArg);
        }

        return $arguments;
    }

    private function trimArgument(Tokens $tokens, array $tokenIndices): array
    {
        // Remove leading and trailing whitespace tokens
        while (!empty($tokenIndices) && $tokens[$tokenIndices[0]]->isWhitespace()) {
            array_shift($tokenIndices);
        }
        while (!empty($tokenIndices) && $tokens[$tokenIndices[count($tokenIndices) - 1]]->isWhitespace()) {
            array_pop($tokenIndices);
        }

        return $tokenIndices;
    }

    private function hasNamedArguments(Tokens $tokens, array $arguments): bool
    {
        foreach ($arguments as $argTokens) {
            foreach ($argTokens as $tokenIndex) {
                // Look for colon indicating named argument
                if ($tokens[$tokenIndex]->equals(':')) {
                    $prevIndex = $tokens->getPrevMeaningfulToken($tokenIndex);
                    if (null !== $prevIndex && $tokens[$prevIndex]->isGivenKind(T_STRING)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function wrapArguments(Tokens $tokens, int $start, int $end, array $arguments): void
    {
        // Get the indentation of the current line
        $indentation = $this->getIndentation($tokens, $start);

        // Collect all argument content
        $argumentContents = [];
        foreach ($arguments as $argTokens) {
            $content = '';
            foreach ($argTokens as $tokenIndex) {
                $content .= $tokens[$tokenIndex]->getContent();
            }
            $argumentContents[] = $content;
        }

        // Clear content between parentheses
        $tokens->clearRange($start + 1, $end - 1);

        // Create replacement tokens
        $replacementTokens = [];

        // Opening newline and indentation
        $replacementTokens[] = new Token([T_WHITESPACE, "\n".$indentation.'    ']);

        foreach ($argumentContents as $index => $content) {
            // Parse the argument content back into tokens
            $argTokens = Tokens::fromCode('<?php '.$content);

            // Skip the opening <?php tag
            for ($i = 1; $i < $argTokens->count(); ++$i) {
                if (!$argTokens[$i]->isGivenKind(T_OPEN_TAG)) {
                    $replacementTokens[] = clone $argTokens[$i];
                }
            }

            // Add comma
            $replacementTokens[] = new Token(',');

            // Add newline and indentation
            if ($index < count($argumentContents) - 1) {
                $replacementTokens[] = new Token([T_WHITESPACE, "\n".$indentation.'    ']);
            } else {
                $replacementTokens[] = new Token([T_WHITESPACE, "\n".$indentation]);
            }
        }

        // Insert all tokens at once
        foreach (array_reverse($replacementTokens) as $token) {
            $tokens->insertAt($start + 1, $token);
        }
    }

    private function getIndentation(Tokens $tokens, int $index): string
    {
        // Find the beginning of the current line
        $currentIndex = $index;
        while ($currentIndex > 0) {
            --$currentIndex;
            if ($tokens[$currentIndex]->isGivenKind(T_WHITESPACE)
                && str_contains($tokens[$currentIndex]->getContent(), "\n")) {
                // Found a newline, the indentation is what comes after it
                $content = $tokens[$currentIndex]->getContent();
                $lastNewlinePos = strrpos($content, "\n");

                return substr($content, $lastNewlinePos + 1);
            }
        }

        // If we reach here, we're on the first line, return empty indentation
        return '';
    }
}
