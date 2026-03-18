<?php

$finder = PhpCsFixer\Finder::create()
    ->in(['src', 'tests'])
    ->exclude('var/cache')
;

$config = new PhpCsFixer\Config();
return $config
    ->setRiskyAllowed(true)
    ->registerCustomFixers([
        new Vrok\SymfonyAddons\PhpCsFixer\WrapNamedMethodArgumentsFixer(),
    ])
    ->setRules([
        // this has priority 100 to be executed before all other
        // fixers, to allow the indentation to be corrected afterwards by the
        // other fixers:
        "VrokSymfonyAddons/wrap_named_method_arguments" => [
            'max_arguments' => 2,
        ],

        // keep close to the Symfony standard
        '@Symfony'               => true,
        '@Symfony:risky'                 => true,

        'attribute_empty_parentheses'    => true,

        // but force alignment of keys/values in array definitions
        'binary_operator_spaces' => [
            'operators' => [
                '=>' => 'align_single_space_minimal_by_scope',
                '='  => null,
            ],
        ],

        'method_argument_space' => [
            'on_multiline' =>  'ensure_fully_multiline',
        ],

        // this would otherwise separate annotations
        'phpdoc_separation'      => [
            'skip_unlisted_annotations' => true,
        ],
    ])
    ->setFinder($finder)
;