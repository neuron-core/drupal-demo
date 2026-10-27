FROM axllent/mailpit:v1.31.6 AS mailpit

FROM drupal:11.4-php8.4-apache-trixie

# Match the container's www-data user to your host user, so files written by
# Apache, Composer and Drush are owned by you on the host (no sudo needed).
ARG UID=1000
ARG GID=1000
RUN groupmod -o -g ${GID} www-data \
 && usermod -o -u ${UID} -g ${GID} www-data \
 && apt-get update \
 && apt-get install -y --no-install-recommends mariadb-client git unzip \
 && rm -rf /var/lib/apt/lists/*

# Route PHP mail() to the Mailpit container (view emails at http://localhost:8025).
COPY --from=mailpit /mailpit /usr/local/bin/mailpit
COPY php.ini /usr/local/etc/php/conf.d/zz-drupal-demo.ini

ENV COMPOSER_HOME=/tmp/composer \
    PATH="/opt/drupal/vendor/bin:${PATH}"

WORKDIR /opt/drupal
