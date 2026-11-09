#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$list_path = __DIR__ . '/rename-old-names.txt';

/**
 * Identifiers that may legitimately contain camelCase or Studly segments.
 *
 * @var array<string, true>
 */
$allow_camel = [
    'ReflectionClass' => true,
    'ReflectionMethod' => true,
    'TestCase' => true,
    'Framework' => true,
    'FilesystemIterator' => true,
    'RecursiveDirectoryIterator' => true,
    'RecursiveIteratorIterator' => true,
    'SplFileInfo' => true,
    'getName' => true,
    'getMethods' => true,
    'getDocComment' => true,
    'assertEquals' => true,
    'assertSame' => true,
    'assertStringEndsWith' => true,
    'GitHub' => true,
    'LaTeX' => true,
    'PHPDoc' => true,
    'HTML' => true,
    'AllowedAttributes' => true,
    'AllowedElements' => true,
    'xhprof_enable' => true,
    'xhprof_disable' => true,
    'XHProfRuns_Default' => true,
    'XHPROF_FLAGS_CPU' => true,
    'XHPROF_FLAGS_MEMORY' => true,
    'inheritDoc' => true,
    'inheritdocs' => true,
    'dataProvider' => true,
    'convertErrorsToExceptions' => true,
    'convertNoticesToExceptions' => true,
    'convertWarningsToExceptions' => true,
    'stopOnFailure' => true,
];

/**
 * @return list<string>
 */
function load_old_names(string $list_path): array
{
    if (!is_readable($list_path)) {
        fwrite(STDERR, "check-rename: missing {$list_path}\n");
        exit(2);
    }

    $lines = file($list_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $names = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $names[] = $line;
    }

    return $names;
}

/**
 * @return list<string>
 */
function scan_paths(string $root): array
{
    $paths = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $rel = substr($file->getPathname(), strlen($root) + 1);
        if (strpos($rel, 'vendor/') === 0 || strpos($rel, '.git/') === 0 || strpos($rel, '.system-test/') === 0) {
            continue;
        }
        if ($rel === 'CHANGELOG.md' || $rel === 'scripts/rename-old-names.txt') {
            continue;
        }
        if ($rel === 'scripts/check-rename.php' || $rel === 'scripts/apply-snake-rename.py' || $rel === 'scripts/apply_lower_snake.py') {
            continue;
        }
        if (preg_match('#^tests/(markdown-data|github-data|extra-data|markdown-ol-start-num-data)/#', $rel)) {
            continue;
        }
        if (!preg_match('/\.(php|md|xml|dist|json)$/', $rel) && $rel !== 'bin/markdown') {
            continue;
        }
        $paths[] = $file->getPathname();
    }

    sort($paths);

    return $paths;
}

/**
 * @param array<string, true> $allow_camel
 *
 * @return list<string>
 */
function find_camel_violations(string $contents, string $path, array $allow_camel): array
{
    $violations = [];
    $patterns = [
        '/\b[a-z]+[A-Z][A-Za-z0-9]*\b/',
        '/\b[a-z]+(?:_[A-Z][a-z0-9]*)+\b/',
    ];
    foreach ($patterns as $pattern) {
        if (!preg_match_all($pattern, $contents, $matches)) {
            continue;
        }
        foreach ($matches[0] as $match) {
            if (isset($allow_camel[$match])) {
                continue;
            }
            if (strpos($match, 'http') === 0 || strpos($match, 'php') === 0) {
                continue;
            }
            $violations[] = sprintf('%s contains camelCase repo identifier %s', $path, $match);
        }
    }

    return $violations;
}

/**
 * @param list<string> $old_names
 */
function find_legacy_violations(array $old_names, array $paths): array
{
    $violations = [];
    foreach ($paths as $path) {
        $contents = file_get_contents($path);
        if ($contents === false) {
            continue;
        }
        foreach ($old_names as $old) {
            if (preg_match('/\b' . preg_quote($old, '/') . '\b/', $contents)) {
                $violations[] = sprintf('%s contains old name %s', $path, $old);
            }
        }
    }

    return $violations;
}

function assert_legacy_paths_gone(string $root): array
{
    $legacy = [
        'GithubMarkdown.php',
        'MarkdownExtra.php',
        'tests/BaseMarkdownTest.php',
        'tests/GithubMarkdownTest.php',
        'tests/MarkdownExtraTest.php',
        'tests/MarkdownOLStartNumTest.php',
        'tests/MarkdownTest.php',
        'tests/ParserTest.php',
        'block/CodeTrait.php',
        'inline/LinkTrait.php',
    ];
    $violations = [];
    foreach ($legacy as $rel) {
        if (is_file($root . '/' . $rel)) {
            $violations[] = "legacy path still exists: {$rel}";
        }
    }

    return $violations;
}

if (in_array('--demo-old-name', $argv, true)) {
    $demo_file = $root . '/.system-test/rename-demo-injection.php';
    if (!is_dir(dirname($demo_file))) {
        mkdir(dirname($demo_file), 0777, true);
    }
    file_put_contents($demo_file, "<?php\n// GithubMarkdown\n");
    $violations = find_legacy_violations(['GithubMarkdown'], [$demo_file]);
    if ($violations === []) {
        fwrite(STDERR, "check-rename: demo injection failed to trigger detection.\n");
        unlink($demo_file);
        exit(1);
    }
    unlink($demo_file);
    fwrite(STDOUT, "check-rename: demo detected injected old name GithubMarkdown\n");
    exit(0);
}

if (in_array('--demo-camel-case', $argv, true)) {
    $demo_file = $root . '/.system-test/rename-camel-demo.php';
    if (!is_dir(dirname($demo_file))) {
        mkdir(dirname($demo_file), 0777, true);
    }
    file_put_contents($demo_file, "<?php\nfunction testParse() {}\n");
    $violations = find_camel_violations(file_get_contents($demo_file), $demo_file, $allow_camel);
    if ($violations === []) {
        fwrite(STDERR, "check-rename: camelCase demo failed to trigger detection.\n");
        unlink($demo_file);
        exit(1);
    }
    unlink($demo_file);
    fwrite(STDOUT, "check-rename: demo detected injected camelCase identifier testParse\n");
    exit(0);
}

$old_names = load_old_names($list_path);
$paths = scan_paths($root);
$violations = array_merge(
    find_legacy_violations($old_names, $paths),
    assert_legacy_paths_gone($root)
);

foreach ($paths as $path) {
    $contents = file_get_contents($path);
    if ($contents === false) {
        continue;
    }
    $violations = array_merge($violations, find_camel_violations($contents, $path, $allow_camel));
}

if ($violations !== []) {
    foreach (array_unique($violations) as $violation) {
        fwrite(STDERR, $violation . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "check-rename: no pre-rename repo-owned names remain\n");
exit(0);
