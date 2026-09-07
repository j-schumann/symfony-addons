<?php

$finder = PhpCsFixer\Finder::create()
    ->in(['src', 'tests'])
    ->exclude('var/cache')
    ->notPath([
        'config/bundles.php',
        'config/reference.php',
    ])
;

$config = new PhpCsFixer\Config();
return $config
    ->setRiskyAllowed(true)
    ->registerCustomFixers([
        new Vrok\SymfonyAddons\PhpCsFixer\WrapNamedMethodArgumentsFixer(),
    ])
    ->setRules([
        // this has priority 100 to be executed before all other fixers, to allow the indentation
        // to be corrected afterwards by the other fixers:
        "VrokSymfonyAddons/wrap_named_method_arguments" => [
            'max_arguments' => 2,
        ],

        '@PHP8x3Migration'                              => true,
        '@PHP8x4Migration'                              => true,

        // keep close to the Symfony standard
        '@Symfony'               => true,
        '@Symfony:risky'         => true,

        'attribute_empty_parentheses'    => true,

        'binary_operator_spaces' => [
            'operators' => [
                // ... but allow alignment of keys/values in array definitions
                '=>' => 'align_single_space_minimal_by_scope',

                // There is no rule to enforce aligned assignments only for class
                // constants, so we can only disable handling assignments completely
                '='  => null,
            ],
        ],

        'method_argument_space' => [
            'on_multiline' =>  'ensure_fully_multiline',
        ],

        'operator_linebreak'                            => [
            'only_booleans' => true,
            'position'      => 'beginning',
        ],

        // this would otherwise separate annotations
        'phpdoc_separation'      => [
            'skip_unlisted_annotations' => true,
        ],
    ])
    ->setFinder($finder)
;