#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$listPath = __DIR__ . '/rename-old-names.txt';

/**
 * @return list<string>
 */
function load_old_names(string $listPath): array
{
    if (!is_readable($listPath)) {
        fwrite(STDERR, "check-rename: missing {$listPath}\n");
        exit(2);
    }

    $lines = file($listPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
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
        if ($rel === 'scripts/check-rename.php' || $rel === 'scripts/apply-snake-rename.py') {
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
 * @param list<string> $oldNames
 */
function find_violations(array $oldNames, array $paths): array
{
    $violations = [];
    foreach ($paths as $path) {
        $contents = file_get_contents($path);
        if ($contents === false) {
            continue;
        }
        foreach ($oldNames as $old) {
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
    $demoFile = $root . '/.system-test/rename-demo-injection.php';
    if (!is_dir(dirname($demoFile))) {
        mkdir(dirname($demoFile), 0777, true);
    }
    file_put_contents($demoFile, "<?php\n// GithubMarkdown\n");
    $violations = find_violations(['GithubMarkdown'], [$demoFile]);
  if ($violations === []) {
        fwrite(STDERR, "check-rename: demo injection failed to trigger detection.\n");
        unlink($demoFile);
        exit(1);
    }
    unlink($demoFile);
    fwrite(STDOUT, "check-rename: demo detected injected old name GithubMarkdown\n");
    exit(0);
}

$oldNames = load_old_names($listPath);
$paths = scan_paths($root);
$violations = array_merge(find_violations($oldNames, $paths), assert_legacy_paths_gone($root));

if ($violations !== []) {
    foreach ($violations as $violation) {
        fwrite(STDERR, $violation . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "check-rename: no pre-rename repo-owned names remain\n");
exit(0);
