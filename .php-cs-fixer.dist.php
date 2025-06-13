<?php

$finder = PhpCsFixer\Finder::create()
    ->in(['src', 'tests'])
    ->exclude('var/cache')
;

$config = new PhpCsFixer\Config();
return $config
    ->registerCustomFixers([
        new Vrok\SymfonyAddons\PhpCsFixer\WrapMethodArgumentsFixer(),
    ])
    ->setRules([
        // keep close to the Symfony standard
        '@Symfony'               => true,

        // but force alignment of keys/values in array definitions
        'binary_operator_spaces' => [
            'operators' => [
                '=>' => 'align_single_space_minimal_by_scope',
                '='  => null,
            ],
        ],

        'VrokSymfonyAddons/method_argument_wrap' => [
            'max_arguments' => 1,
            'named_arguments_only' => true,
        ],

        // this would otherwise separate annotations
        'phpdoc_separation'      => [
            'skip_unlisted_annotations' => true,
        ],
    ])
    ->setFinder($finder)
;
