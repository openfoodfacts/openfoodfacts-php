<?php

$config = new PhpCsFixer\Config();
$finder = $config->getFinder();
$finder
    ->in(['src','tests'])
;

return $config
    ->setRules([
        '@PSR12' => true,
        'array_indentation' => true,
        'array_syntax' => ['syntax' => 'short'],
        'single_quote' => true,
        'blank_line_before_statement' => true,
        'no_spaces_around_offset' => true,
        'no_unused_imports' => true,
        'ternary_operator_spaces' => true,
    ])
    ->setUsingCache(false)
    ;