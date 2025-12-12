<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$rules = [
    '@PhpCsFixer' => true,
    'increment_style' => [
        'style' => 'post',
    ],
    'multiline_whitespace_before_semicolons' => [
        'strategy' => 'no_multi_line',
    ],
    'psr_autoloading' => true,
    'strict_comparison' => true,
    'strict_param' => true,
    'ternary_to_null_coalescing' => true,
    'yoda_style' => false,
];

$finder = Finder::create()
    ->exclude([
        'assets',
        'public_html',
        'storage',
        'templates',
    ])
    ->in(__DIR__);

$fixer = (new Config())
    ->setRiskyAllowed(true)
    ->setRules($rules)
    ->setFinder($finder);

return $fixer;
