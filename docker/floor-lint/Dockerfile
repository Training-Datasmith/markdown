FROM debian:buster-slim@sha256:bb3dc79fddbca7e8903248ab916bb775c96ec61014b3d02b4f06043b604726dc

ENV DEBIAN_FRONTEND=noninteractive
ENV PHP_VERSION=5.4.45
ENV PHP_TARBALL_SHA256=4e0d28b1554c95cfaea6fa2b64aac85433f158ce72bb571bcd5574f98f4c6582

COPY ca-bundle.crt /etc/ssl/certs/ca-certificates.crt
ENV SSL_CERT_FILE=/etc/ssl/certs/ca-certificates.crt

RUN printf 'deb https://archive.debian.org/debian buster main\n' > /etc/apt/sources.list \
    && printf 'deb https://archive.debian.org/debian-security buster/updates main\n' >> /etc/apt/sources.list \
    && printf 'Acquire::Check-Valid-Until "false";\n' > /etc/apt/apt.conf.d/99no-check-valid-until \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        bzip2 \
        build-essential \
        libxml2-dev \
        libpcre3-dev \
        pkg-config \
    && curl -fsSL -o /tmp/php.tar.bz2 "https://www.php.net/distributions/php-${PHP_VERSION}.tar.bz2" \
    && echo "${PHP_TARBALL_SHA256}  /tmp/php.tar.bz2" | sha256sum -c - \
    && tar -xjf /tmp/php.tar.bz2 -C /tmp \
    && cd "/tmp/php-${PHP_VERSION}" \
    && ./configure --disable-all --enable-cli --without-pear \
    && make -j"$(nproc)" \
    && make install \
    && /usr/local/bin/php -v | head -1 | grep -Fq "PHP 5.4.45" \
    && rm -rf "/tmp/php-${PHP_VERSION}" /tmp/php.tar.bz2 \
    && apt-get purge -y --auto-remove build-essential pkg-config bzip2 curl libxml2-dev libpcre3-dev \
    && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
