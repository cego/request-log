<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src');

return PhpCsFixer\Config::create()
    ->setRules([
        '@PSR2'                            => true,
        'no_blank_lines_after_phpdoc'      => true,
        'no_empty_phpdoc'                  => true,
        'no_unused_imports'                => true,
        'phpdoc_indent'                    => true,
        'phpdoc_trim'                      => true,
        'phpdoc_scalar'                    => true,
        'phpdoc_separation'                => true,
        'whitespace_after_comma_in_array'  => true,
        'method_separation'                => true,
        'not_operator_with_space'          => true,
        'no_extra_consecutive_blank_lines' => [
            'tokens' => ['extra']
        ],
        'concat_space' => [
            'spacing' => 'one'
        ],
        'binary_operator_spaces' => [
            'align_double_arrow' => true
        ],
        'blank_line_before_statement' => [
            'statements' => ['break', 'continue', 'declare', 'return', 'throw', 'try', 'foreach', 'if', 'switch', 'do', 'while']
        ],
        'ordered_imports' => [
            'sortAlgorithm' => 'length'
        ]
    ])
    ->setFinder($finder);
