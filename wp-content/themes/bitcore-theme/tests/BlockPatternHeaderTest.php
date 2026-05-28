<?php
/**
 * Standalone regression test for BITCORE theme block pattern files.
 * Run: php wp-content/themes/bitcore-theme/tests/BlockPatternHeaderTest.php
 */

$theme_dir = dirname(__DIR__);
$pattern_dir = $theme_dir . '/patterns';
$files = glob($pattern_dir . '/*.php') ?: [];
$assertions = 0;
$failures = [];

$assert = function (bool $condition, string $message) use (&$assertions, &$failures): void {
    $assertions++;
    if (!$condition) {
        $failures[] = $message;
    }
};

$assert(count($files) > 0, 'pattern_files_exist');

foreach ($files as $file) {
    $content = file_get_contents($file);
    $base = basename($file);
    $assert(preg_match('/\*\s+Title:\s+\S+/m', $content) === 1, "$base has Title header");
    $assert(preg_match('/\*\s+Slug:\s+bitcore-theme\/[a-z0-9-]+/m', $content) === 1, "$base has valid Slug header");
    $assert(preg_match('/\*\s+Categories:\s+bitcore-theme/m', $content) === 1, "$base has Categories header");
    $assert(strpos($content, 'register_block_pattern(') === false, "$base does not manually register itself");
}

$functions = file_get_contents($theme_dir . '/functions.php');
$assert(strpos($functions, 'require_once $file') === false, 'functions.php does not require pattern files manually');
$assert(strpos($functions, 'register_block_pattern_category(') !== false, 'functions.php still registers pattern category');


$header_path = $theme_dir . '/parts/header.html';
$assert(file_exists($header_path), 'header_part_exists');
$header = file_get_contents($header_path);
$assert(strpos($header, '"text":"Sign In"') === false, 'header_button_does_not_use_invalid_text_attribute');
$assert(strpos($header, '<!-- wp:button {"className":"bitcore-btn bitcore-btn--ghost is-style-outline"} -->') !== false, 'header_button_has_opening_block_comment');
$assert(strpos($header, '<!-- /wp:button -->') !== false, 'header_button_has_closing_block_comment');
$assert(strpos($header, '>Sign In<') !== false, 'header_button_text_is_inner_markup');

if ($failures) {
    echo "FAIL assertions=$assertions failures=" . count($failures) . "\n";
    foreach ($failures as $failure) {
        echo " - $failure\n";
    }
    exit(1);
}

echo "PASS assertions=$assertions\n";
