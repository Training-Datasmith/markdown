#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Verify org cluster packages and Packagist PHPUnit pin expectations.
 */

const ORG_FORBIDDEN_REFS = [
    'a37d66fabf01af0eedabffe271b70dbe71723001',
    '698cbbc1e75a1ef1968f9cbfec89be056b288d0f',
];

const FORK_URL_MARKER = 'github.com/Training-Datasmith/';

function load_installed_php(string $root): array
{
    $installedPhp = $root . '/vendor/composer/installed.php';
    if (!is_readable($installedPhp)) {
        fwrite(STDERR, "check-cluster-pins: vendor/composer/installed.php not found; run composer update first.\n");
        exit(1);
    }

    /** @var array{versions?: array<string, array<string, mixed>>} $installed */
    $installed = require $installedPhp;

    return $installed['versions'] ?? [];
}

function load_installed_json_packages(string $root): array
{
    $installedJson = $root . '/vendor/composer/installed.json';
    if (!is_readable($installedJson)) {
        fwrite(STDERR, "check-cluster-pins: vendor/composer/installed.json not found; run composer update first.\n");
        exit(1);
    }

    $raw = file_get_contents($installedJson);
    if ($raw === false) {
        fwrite(STDERR, "check-cluster-pins: could not read installed.json.\n");
        exit(1);
    }

    /** @var array<string, mixed>|null $data */
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        fwrite(STDERR, "check-cluster-pins: could not decode installed.json.\n");
        exit(1);
    }
    $packages = array_merge($data['packages'] ?? [], $data['packages-dev'] ?? []);

  /** @var array<string, array<string, mixed>> $byName */
    $byName = [];
    foreach ($packages as $package) {
        if (!isset($package['name'])) {
            continue;
        }
        $byName[(string) $package['name']] = $package;
    }

    return $byName;
}

/**
 * @param array<string, mixed> $installedPhpVersions
 * @param array<string, array<string, mixed>> $jsonPackages
 */
function assert_example_pin(array $installedPhpVersions, array $jsonPackages): bool
{
    $name = 'example/cluster-pin';
    $alias = '8.5.52';
    $goodRef = '0123456789abcdef0123456789abcdef01234567';

    $phpVersions = $installedPhpVersions;
    $phpVersions[$name] = [
        'reference' => $goodRef,
        'aliases' => [$alias],
    ];

    $json = $jsonPackages;
    $json[$name] = [
        'name' => $name,
        'source' => ['reference' => $goodRef],
    ];

    if (!assert_expected_org_package($name, $alias, $goodRef, $phpVersions, $json)) {
        fwrite(STDERR, "check-cluster-pins: wrong-SHA demo failed to accept valid example pin.\n");
        return false;
    }

    $bad = $phpVersions;
    $bad[$name]['reference'] = 'ffffffffffffffffffffffffffffffffffffffff';
    if (assert_expected_org_package($name, $alias, $goodRef, $bad, $json, true)) {
        fwrite(STDERR, "check-cluster-pins: wrong-SHA demo did not reject installed.php reference.\n");
        return false;
    }
    fwrite(STDOUT, "check-cluster-pins: wrong-SHA demo rejected installed.php reference\n");

    $badJson = $jsonPackages;
    $badJson[$name] = [
        'name' => $name,
        'source' => ['reference' => 'ffffffffffffffffffffffffffffffffffffffff'],
    ];
    if (assert_expected_org_package($name, $alias, $goodRef, $phpVersions, $badJson, true)) {
        fwrite(STDERR, "check-cluster-pins: wrong-SHA demo did not reject installed.json source.reference.\n");
        return false;
    }
    fwrite(STDOUT, "check-cluster-pins: wrong-SHA demo rejected installed.json source.reference\n");

    return true;
}

/**
 * @param array<string, array<string, mixed>> $installedPhpVersions
 * @param array<string, array<string, mixed>> $jsonPackages
 */
function assert_expected_org_package(
    string $name,
    string $alias,
    string $expectedRef,
    array $installedPhpVersions,
    array $jsonPackages,
    bool $quiet = false
): bool {
    $failed = false;
    $stderr = static function (string $message) use ($quiet): void {
        if (!$quiet) {
            fwrite(STDERR, $message);
        }
    };

    if (!isset($installedPhpVersions[$name])) {
        $stderr(sprintf("check-cluster-pins: %s is not installed (installed.php).\n", $name));
        return false;
    }

    $pkg = $installedPhpVersions[$name];
    $aliases = $pkg['aliases'] ?? [];
    if (!is_array($aliases) || !in_array($alias, $aliases, true)) {
        $stderr(sprintf(
            "check-cluster-pins: %s alias mismatch (expected %s in aliases %s).\n",
            $name,
            $alias,
            json_encode($aliases)
        ));
        $failed = true;
    }

    $reference = $pkg['reference'] ?? null;
    if (!is_string($reference) || $reference === '') {
        $stderr(sprintf("check-cluster-pins: %s has no reference in installed.php.\n", $name));
        $failed = true;
    } elseif (!hash_equals($expectedRef, $reference)) {
        $stderr(sprintf(
            "check-cluster-pins: %s installed.php reference mismatch (expected %s, got %s).\n",
            $name,
            $expectedRef,
            $reference
        ));
        $failed = true;
    }

    if (!isset($jsonPackages[$name]['source']['reference'])) {
        $stderr(sprintf("check-cluster-pins: %s missing source.reference in installed.json.\n", $name));
        $failed = true;
    } else {
        $jsonRef = (string) $jsonPackages[$name]['source']['reference'];
        if (!hash_equals($expectedRef, $jsonRef)) {
            $stderr(sprintf(
                "check-cluster-pins: %s installed.json reference mismatch (expected %s, got %s).\n",
                $name,
                $expectedRef,
                $jsonRef
            ));
            $failed = true;
        }
    }

    return !$failed;
}

/**
 * @param array<string, array<string, mixed>> $installedPhpVersions
 * @param array<string, array<string, mixed>> $jsonPackages
 */
function assert_no_org_forks_and_packagist_phpunit(array $installedPhpVersions, array $jsonPackages): bool
{
    $failed = false;
    $orgCount = 0;

    foreach ($jsonPackages as $name => $package) {
        if ($name === '__root__' || $name === 'cebe/markdown') {
            continue;
        }

        $source = $package['source'] ?? [];
        $url = isset($source['url']) ? (string) $source['url'] : '';
        if (strpos($url, FORK_URL_MARKER) !== false) {
            fwrite(STDERR, sprintf("check-cluster-pins: unexpected org fork installed: %s (%s)\n", $name, $url));
            ++$orgCount;
            $failed = true;
        }
    }

    if (!isset($installedPhpVersions['phpunit/phpunit'])) {
        fwrite(STDERR, "check-cluster-pins: phpunit/phpunit is not installed.\n");
        return false;
    }

    $phpunit = $installedPhpVersions['phpunit/phpunit'];
    $reference = isset($phpunit['reference']) ? (string) $phpunit['reference'] : '';
    $aliases = $phpunit['aliases'] ?? [];
    if (!is_array($aliases) || $aliases !== []) {
        fwrite(STDERR, sprintf(
            "check-cluster-pins: phpunit/phpunit aliases must be empty, got %s.\n",
            json_encode($aliases)
        ));
        $failed = true;
    }

    foreach (ORG_FORBIDDEN_REFS as $forbidden) {
        if (strcasecmp($reference, $forbidden) === 0) {
            fwrite(STDERR, "check-cluster-pins: phpunit/phpunit is pinned to forbidden org fork SHA {$forbidden}.\n");
            $failed = true;
        }
    }

    $jsonPhpunit = $jsonPackages['phpunit/phpunit'] ?? null;
    if (!is_array($jsonPhpunit)) {
        fwrite(STDERR, "check-cluster-pins: phpunit/phpunit missing from installed.json.\n");
        return false;
    }

    $jsonUrl = (string) ($jsonPhpunit['source']['url'] ?? '');
    if (strpos($jsonUrl, 'github.com/sebastianbergmann/phpunit') === false) {
        fwrite(STDERR, sprintf("check-cluster-pins: phpunit/phpunit source url is not Packagist upstream: %s\n", $jsonUrl));
        $failed = true;
    }

    $jsonRef = (string) ($jsonPhpunit['source']['reference'] ?? '');
    if ($reference !== '' && $jsonRef !== '' && !hash_equals($reference, $jsonRef)) {
        fwrite(STDERR, sprintf(
            "check-cluster-pins: phpunit/phpunit reference mismatch installed.php=%s installed.json=%s\n",
            $reference,
            $jsonRef
        ));
        $failed = true;
    }

    foreach (ORG_FORBIDDEN_REFS as $forbidden) {
        if (strcasecmp($jsonRef, $forbidden) === 0) {
            fwrite(STDERR, "check-cluster-pins: phpunit/phpunit installed.json points at org fork SHA.\n");
            $failed = true;
        }
    }

    $version = (string) ($jsonPhpunit['version'] ?? $phpunit['pretty_version'] ?? '');
    if (!preg_match('/^8\.5\.(5[2-9]|[6-9]\d)$/', $version)) {
        fwrite(STDERR, sprintf("check-cluster-pins: phpunit/phpunit version %s is outside patched 8.5.x line.\n", $version));
        $failed = true;
    }

    if ($failed) {
        return false;
    }

    fwrite(STDOUT, sprintf(
        "check-cluster-pins: %d org pins matched; phpunit/phpunit %s from Packagist (not an org fork)\n",
        $orgCount,
        $version
    ));

    return true;
}

$root = dirname(__DIR__);
$installedPhp = load_installed_php($root);
$jsonPackages = load_installed_json_packages($root);

if (!assert_example_pin($installedPhp, $jsonPackages)) {
    exit(1);
}

if (!assert_no_org_forks_and_packagist_phpunit($installedPhp, $jsonPackages)) {
    exit(1);
}

exit(0);
