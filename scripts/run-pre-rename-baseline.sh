#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
IMAGE_TAG="markdown-system-test:7.2"
USE_SUDO_DOCKER="${USE_SUDO_DOCKER:-0}"

docker_cmd() {
    if [[ "${USE_SUDO_DOCKER}" == "1" ]]; then
        sudo docker "$@"
    else
        docker "$@"
    fi
}

docker_cmd build -f "${ROOT}/docker/system-test/Dockerfile" -t "${IMAGE_TAG}" "${ROOT}"

docker_cmd run --rm \
    -v "${ROOT}:/app" \
    -w /app \
    "${IMAGE_TAG}" \
    bash -lc '
set -euo pipefail
export COMPOSER_ALLOW_SUPERUSER=1
COMPOSER_URL="https://getcomposer.org/download/2.8.10/composer.phar"
COMPOSER_SHA256="28dbb6bd8bef31479c7985b774c130a8bda37dbe63c35b56f6cb6bc377427573"
curl -fsSL -o /tmp/composer.phar "${COMPOSER_URL}"
echo "${COMPOSER_SHA256}  /tmp/composer.phar" | sha256sum -c -
php /tmp/composer.phar update --no-interaction --prefer-dist --no-progress
vendor/bin/phpunit --colors=never --no-coverage 2>&1 | tee /app/.system-test/pre-rename-phpunit.out
'
