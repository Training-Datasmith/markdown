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
    $installed_php = $root . '/vendor/composer/installed.php';
    if (!is_readable($installed_php)) {
        fwrite(STDERR, "check-cluster-pins: vendor/composer/installed.php not found; run composer update first.\n");
        exit(1);
    }

    /** @var array{versions?: array<string, array<string, mixed>>} $installed */
    $installed = require $installed_php;

    return $installed['versions'] ?? [];
}

function load_installed_json_packages(string $root): array
{
    $installed_json = $root . '/vendor/composer/installed.json';
    if (!is_readable($installed_json)) {
        fwrite(STDERR, "check-cluster-pins: vendor/composer/installed.json not found; run composer update first.\n");
        exit(1);
    }

    $raw = file_get_contents($installed_json);
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

  /** @var array<string, array<string, mixed>> $by_name */
    $by_name = [];
    foreach ($packages as $package) {
        if (!isset($package['name'])) {
            continue;
        }
        $by_name[(string) $package['name']] = $package;
    }

    return $by_name;
}

/**
 * @param array<string, mixed> $installed_php_versions
 * @param array<string, array<string, mixed>> $json_packages
 */
function assert_example_pin(array $installed_php_versions, array $json_packages): bool
{
    $name = 'example/cluster-pin';
    $alias = '8.5.52';
    $good_ref = '0123456789abcdef0123456789abcdef01234567';

    $php_versions = $installed_php_versions;
    $php_versions[$name] = [
        'reference' => $good_ref,
        'aliases' => [$alias],
    ];

    $json = $json_packages;
    $json[$name] = [
        'name' => $name,
        'source' => ['reference' => $good_ref],
    ];

    if (!assert_expected_org_package($name, $alias, $good_ref, $php_versions, $json)) {
        fwrite(STDERR, "check-cluster-pins: wrong-SHA demo failed to accept valid example pin.\n");
        return false;
    }

    $bad = $php_versions;
    $bad[$name]['reference'] = 'ffffffffffffffffffffffffffffffffffffffff';
    if (assert_expected_org_package($name, $alias, $good_ref, $bad, $json, true)) {
        fwrite(STDERR, "check-cluster-pins: wrong-SHA demo did not reject installed.php reference.\n");
        return false;
    }
    fwrite(STDOUT, "check-cluster-pins: wrong-SHA demo rejected installed.php reference\n");

    $bad_json = $json_packages;
    $bad_json[$name] = [
        'name' => $name,
        'source' => ['reference' => 'ffffffffffffffffffffffffffffffffffffffff'],
    ];
    if (assert_expected_org_package($name, $alias, $good_ref, $php_versions, $bad_json, true)) {
        fwrite(STDERR, "check-cluster-pins: wrong-SHA demo did not reject installed.json source.reference.\n");
        return false;
    }
    fwrite(STDOUT, "check-cluster-pins: wrong-SHA demo rejected installed.json source.reference\n");

    return true;
}

/**
 * @param array<string, array<string, mixed>> $installed_php_versions
 * @param array<string, array<string, mixed>> $json_packages
 */
function assert_expected_org_package(
    string $name,
    string $alias,
    string $expected_ref,
    array $installed_php_versions,
    array $json_packages,
    bool $quiet = false
): bool {
    $failed = false;
    $stderr = static function (string $message) use ($quiet): void {
        if (!$quiet) {
            fwrite(STDERR, $message);
        }
    };

    if (!isset($installed_php_versions[$name])) {
        $stderr(sprintf("check-cluster-pins: %s is not installed (installed.php).\n", $name));
        return false;
    }

    $pkg = $installed_php_versions[$name];
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
    } elseif (!hash_equals($expected_ref, $reference)) {
        $stderr(sprintf(
            "check-cluster-pins: %s installed.php reference mismatch (expected %s, got %s).\n",
            $name,
            $expected_ref,
            $reference
        ));
        $failed = true;
    }

    if (!isset($json_packages[$name]['source']['reference'])) {
        $stderr(sprintf("check-cluster-pins: %s missing source.reference in installed.json.\n", $name));
        $failed = true;
    } else {
        $json_ref = (string) $json_packages[$name]['source']['reference'];
        if (!hash_equals($expected_ref, $json_ref)) {
            $stderr(sprintf(
                "check-cluster-pins: %s installed.json reference mismatch (expected %s, got %s).\n",
                $name,
                $expected_ref,
                $json_ref
            ));
            $failed = true;
        }
    }

    return !$failed;
}

/**
 * @param array<string, array<string, mixed>> $installed_php_versions
 * @param array<string, array<string, mixed>> $json_packages
 */
function assert_no_org_forks_and_packagist_phpunit(array $installed_php_versions, array $json_packages): bool
{
    $failed = false;
    $org_count = 0;

    foreach ($json_packages as $name => $package) {
        if ($name === '__root__' || $name === 'cebe/markdown') {
            continue;
        }

        $source = $package['source'] ?? [];
        $url = isset($source['url']) ? (string) $source['url'] : '';
        if (strpos($url, FORK_URL_MARKER) !== false) {
            fwrite(STDERR, sprintf("check-cluster-pins: unexpected org fork installed: %s (%s)\n", $name, $url));
            ++$org_count;
            $failed = true;
        }
    }

    if (!isset($installed_php_versions['phpunit/phpunit'])) {
        fwrite(STDERR, "check-cluster-pins: phpunit/phpunit is not installed.\n");
        return false;
    }

    $phpunit = $installed_php_versions['phpunit/phpunit'];
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

    $json_phpunit = $json_packages['phpunit/phpunit'] ?? null;
    if (!is_array($json_phpunit)) {
        fwrite(STDERR, "check-cluster-pins: phpunit/phpunit missing from installed.json.\n");
        return false;
    }

    $json_url = (string) ($json_phpunit['source']['url'] ?? '');
    if (strpos($json_url, 'github.com/sebastianbergmann/phpunit') === false) {
        fwrite(STDERR, sprintf("check-cluster-pins: phpunit/phpunit source url is not Packagist upstream: %s\n", $json_url));
        $failed = true;
    }

    $json_ref = (string) ($json_phpunit['source']['reference'] ?? '');
    if ($reference !== '' && $json_ref !== '' && !hash_equals($reference, $json_ref)) {
        fwrite(STDERR, sprintf(
            "check-cluster-pins: phpunit/phpunit reference mismatch installed.php=%s installed.json=%s\n",
            $reference,
            $json_ref
        ));
        $failed = true;
    }

    foreach (ORG_FORBIDDEN_REFS as $forbidden) {
        if (strcasecmp($json_ref, $forbidden) === 0) {
            fwrite(STDERR, "check-cluster-pins: phpunit/phpunit installed.json points at org fork SHA.\n");
            $failed = true;
        }
    }

    $version = (string) ($json_phpunit['version'] ?? $phpunit['pretty_version'] ?? '');
    if (!preg_match('/^8\.5\.(5[2-9]|[6-9]\d)$/', $version)) {
        fwrite(STDERR, sprintf("check-cluster-pins: phpunit/phpunit version %s is outside patched 8.5.x line.\n", $version));
        $failed = true;
    }

    if ($failed) {
        return false;
    }

    fwrite(STDOUT, sprintf(
        "check-cluster-pins: %d org pins matched; phpunit/phpunit %s from Packagist (not an org fork)\n",
        $org_count,
        $version
    ));

    return true;
}

$root = dirname(__DIR__);
$installed_php = load_installed_php($root);
$json_packages = load_installed_json_packages($root);

if (!assert_example_pin($installed_php, $json_packages)) {
    exit(1);
}

if (!assert_no_org_forks_and_packagist_phpunit($installed_php, $json_packages)) {
    exit(1);
}

exit(0);
