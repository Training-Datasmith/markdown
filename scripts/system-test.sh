#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
IMAGE_TAG="markdown-system-test:7.2"
FLOOR_IMAGE_TAG="markdown-floor:5.4.45"
USE_SUDO_DOCKER="${USE_SUDO_DOCKER:-0}"
OUT_DIR="${ROOT}/.system-test"
mkdir -p "${OUT_DIR}"

docker_cmd() {
    if [[ "${USE_SUDO_DOCKER}" == "1" ]]; then
        sudo docker "$@"
    else
        docker "$@"
    fi
}

strip_ansi() {
    sed -E 's/\x1b\[[0-9;]*[[:alpha:]]//g'
}

fail_if_deprecation_output() {
    local label="$1"
    local file="$2"
    local stripped
    stripped="$(mktemp)"
    strip_ansi <"${file}" >"${stripped}"
    if grep -E '^(PHP )?(Warning|Deprecated|Notice):' "${stripped}" >/dev/null; then
        echo "${label}: output contains Warning, Deprecated, or Notice lines" >&2
        grep -E '^(PHP )?(Warning|Deprecated|Notice):' "${stripped}" >&2 || true
        rm -f "${stripped}"
        exit 1
    fi
    if grep -E '(^|[[:space:]])(PHP[[:space:]]+)?(Warning|Deprecated|Notice):' "${stripped}" >/dev/null; then
        echo "${label}: output contains embedded Warning, Deprecated, or Notice lines" >&2
        rm -f "${stripped}"
        exit 1
    fi
    rm -f "${stripped}"
}

require_stripped_line() {
    local file="$1"
    local pattern="$2"
    local label="$3"
    local stripped
    stripped="$(mktemp)"
    strip_ansi <"${file}" >"${stripped}"
    if ! grep -Eq "${pattern}" "${stripped}"; then
        echo "${label}: missing anchored line matching ${pattern}" >&2
        rm -f "${stripped}"
        exit 1
    fi
    rm -f "${stripped}"
}

TESTS_BASELINE="$(awk -F= '/^tests=/{print $2}' "${ROOT}/scripts/suite-baseline.txt")"
ASSERTIONS_BASELINE="$(awk -F= '/^assertions=/{print $2}' "${ROOT}/scripts/suite-baseline.txt")"
OK_PATTERN="^OK \\(${TESTS_BASELINE} tests, ${ASSERTIONS_BASELINE} assertions\\)$"

LIBRARY_FILES=(
    Parser.php
    Markdown.php
    Markdown_Extra.php
    Github_Markdown.php
    block/Code_Trait.php
    block/Fenced_Code_Trait.php
    block/Headline_Trait.php
    block/Html_Trait.php
    block/List_Trait.php
    block/Quote_Trait.php
    block/Rule_Trait.php
    block/Table_Trait.php
    inline/Code_Trait.php
    inline/Emph_Strong_Trait.php
    inline/Link_Trait.php
    inline/Strikeout_Trait.php
    inline/Url_Link_Trait.php
    bin/markdown
    examples/basic_rendering.php
)

docker_cmd build -f "${ROOT}/docker/system-test/Dockerfile" -t "${IMAGE_TAG}" "${ROOT}"
docker_cmd build -f "${ROOT}/docker/floor-lint/Dockerfile" -t "${FLOOR_IMAGE_TAG}" "${ROOT}/docker/floor-lint"

if ! docker_cmd run --rm \
    -v "${ROOT}:/app" \
    -w /app \
    "${IMAGE_TAG}" \
    bash -lc "
set -euo pipefail
php -r 'echo \"php-test: \".PHP_VERSION.\"\\n\";' | tee /app/.system-test/php-version.txt
php -r '
\$v = PHP_VERSION;
if (version_compare(\$v, \"7.2.0\", \"<\") || version_compare(\$v, \"7.3.0\", \">=\")) {
    fwrite(STDERR, \"Expected PHP 7.2.x, got {\$v}\\n\");
    exit(1);
}
'
for ext in ctype dom json mbstring phar tokenizer xml xmlwriter; do
    php -m | grep -qi \"^\${ext}\$\" || { echo \"Missing extension: \${ext}\" >&2; exit 1; }
done
echo \"php-extensions: ctype dom json mbstring phar tokenizer xml xmlwriter\"
export COMPOSER_ALLOW_SUPERUSER=1
git config --global --add safe.directory /app
COMPOSER_URL=\"https://getcomposer.org/download/2.8.10/composer.phar\"
COMPOSER_SHA256=\"28dbb6bd8bef31479c7985b774c130a8bda37dbe63c35b56f6cb6bc377427573\"
curl -fsSL -o /tmp/composer.phar \"\${COMPOSER_URL}\"
echo \"\${COMPOSER_SHA256}  /tmp/composer.phar\" | sha256sum -c -
set +e
php /tmp/composer.phar update --no-interaction --prefer-dist --no-progress > /app/.system-test/composer-update.out 2>&1
composer_status=\$?
set -e
cat /app/.system-test/composer-update.out
test \"\${composer_status}\" -eq 0
echo \"composer-update: exit 0 with no Warning, Deprecated, or Notice lines\"
set +e
php /tmp/composer.phar audit --no-interaction > /app/.system-test/composer-audit.out 2>&1
audit_status=\$?
set -e
cat /app/.system-test/composer-audit.out
test \"\${audit_status}\" -eq 0
php scripts/check-cluster-pins.php > /app/.system-test/pins.out 2>&1
cat /app/.system-test/pins.out
php scripts/check-rename.php --demo-old-name > /app/.system-test/rename.out 2>&1
php scripts/check-rename.php --demo-camel-case >> /app/.system-test/rename.out 2>&1
php scripts/check-rename.php >> /app/.system-test/rename.out 2>&1
cat /app/.system-test/rename.out
set +e
vendor/bin/phpunit --colors=never --no-coverage > /app/.system-test/phpunit.out 2>&1
phpunit_status=\$?
set -e
cat /app/.system-test/phpunit.out
test \"\${phpunit_status}\" -eq 0
"; then
    exit 1
fi

fail_if_deprecation_output "composer-update" "${OUT_DIR}/composer-update.out"
fail_if_deprecation_output "composer-audit" "${OUT_DIR}/composer-audit.out"
fail_if_deprecation_output "pins" "${OUT_DIR}/pins.out"
fail_if_deprecation_output "rename" "${OUT_DIR}/rename.out"
fail_if_deprecation_output "phpunit" "${OUT_DIR}/phpunit.out"

require_stripped_line "${OUT_DIR}/composer-audit.out" '^No security vulnerability advisories found\.$' "composer audit"
if strip_ansi <"${OUT_DIR}/composer-audit.out" | grep -Eq '^Found [0-9]+ abandoned package'; then
    echo "composer audit: abandoned packages reported" >&2
    exit 1
fi
if strip_ansi <"${OUT_DIR}/composer-audit.out" | grep -Eq '^Found [0-9]+ (ignored )?security vulnerability'; then
    echo "composer audit: vulnerabilities reported" >&2
    exit 1
fi

require_stripped_line "${OUT_DIR}/pins.out" '^check-cluster-pins: wrong-SHA demo rejected installed\.php reference$' "pins"
require_stripped_line "${OUT_DIR}/pins.out" '^check-cluster-pins: wrong-SHA demo rejected installed\.json source\.reference$' "pins"
require_stripped_line "${OUT_DIR}/pins.out" '^check-cluster-pins: 0 org pins matched; phpunit/phpunit 8\.5\.(5[2-9]|[6-9][0-9]{1,2}) from Packagist \(not an org fork\)$' "pins"

require_stripped_line "${OUT_DIR}/rename.out" '^check-rename: demo detected injected old name GithubMarkdown$' "rename"
require_stripped_line "${OUT_DIR}/rename.out" '^check-rename: demo detected injected camelCase identifier testParse$' "rename"
require_stripped_line "${OUT_DIR}/rename.out" '^check-rename: no pre-rename repo-owned names remain$' "rename"

require_stripped_line "${OUT_DIR}/phpunit.out" "${OK_PATTERN}" "phpunit"

FLOOR_OUT="${OUT_DIR}/php-floor.out"
: >"${FLOOR_OUT}"
docker_cmd run --rm \
    -v "${ROOT}:/app" \
    -w /app \
    "${FLOOR_IMAGE_TAG}" \
    bash -lc "/usr/local/bin/php -r 'echo \"php-floor: \".PHP_VERSION.\"\\n\";'" | tee -a "${FLOOR_OUT}"

lint_count=0
for rel in "${LIBRARY_FILES[@]}"; do
    if ! docker_cmd run --rm \
        -v "${ROOT}:/app" \
        -w /app \
        "${FLOOR_IMAGE_TAG}" \
        /usr/local/bin/php -l "/app/${rel}" >>"${FLOOR_OUT}" 2>&1; then
        echo "php -l failed for ${rel}" >&2
        exit 1
    fi
    lint_count=$((lint_count + 1))
done

fail_if_deprecation_output "php-floor" "${FLOOR_OUT}"
require_stripped_line "${FLOOR_OUT}" '^php-floor: 5\.4\.45$' "php-floor"

lint_matches="$(strip_ansi <"${FLOOR_OUT}" | grep -Ec '^No syntax errors detected in .+$' || true)"
if [[ "${lint_matches}" -ne 19 ]]; then
    echo "php -l: expected 19 anchored success lines, got ${lint_matches}" >&2
    exit 1
fi

echo "php-lint: No syntax errors detected in ${lint_count} library files on PHP 5.4.45"
echo "system-test: all steps passed."
