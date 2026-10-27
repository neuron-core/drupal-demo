#!/usr/bin/env bash
# Builds the whole demo from scratch: containers, Drupal install, demo data.
# WARNING: wipes the existing database (all site content and users).
set -euo pipefail
cd "$(dirname "$0")/.."

set -a; source .env; set +a
export UID GID="$(id -g)"

run()   { docker compose exec -T --user www-data drupal "$@"; }
drush() { run drush "$@"; }

echo "==> Building and starting containers"
docker compose down -v --remove-orphans
docker compose build
if [ ! -f app/vendor/autoload.php ]; then
  echo "==> Installing PHP dependencies (Composer)"
  docker compose run --rm --no-deps --user www-data drupal composer install --no-interaction
fi
# The worker starts last, once Drupal is installed.
docker compose up -d --wait db drupal mercure adminer mailpit

echo "==> Installing Drupal with the Umami demo profile"
chmod -R u+w app/web/sites/default
rm -f app/web/sites/default/settings.php
drush site:install demo_umami -y \
  --db-url="mysql://${DB_USER}:${DB_PASSWORD}@db/${DB_NAME}" \
  --account-name=admin --account-pass=admin --account-mail=admin@example.com \
  --site-name="Drupal Demo" --site-mail=noreply@example.com
chmod u+w app/web/sites/default app/web/sites/default/settings.php
cat >> app/web/sites/default/settings.php <<'PHP'

// Local Docker demo: accept requests for localhost only.
$settings['trusted_host_patterns'] = ['^localhost$', '^127\.0\.0\.1$'];

// The AI module re-encodes tool arguments as HTML to filter links, which turns
// "a > b" into "a &gt; b" in SQL and article bodies: trust the model's output.
$settings['ai_output']['full_trust_mode'] = TRUE;
PHP
chmod a-w app/web/sites/default/settings.php app/web/sites/default

echo "==> Enabling comments on articles and Devel Generate"
drush recipe core/recipes/article_comment
drush pm:install devel_generate statistics -y
drush php:script /opt/scripts/demo-content.php -- before

echo "==> Generating demo data"
drush devel-generate:users 10 --roles=editor
drush devel-generate:users 15 --roles=author
drush devel-generate:users 25
drush devel-generate:terms 30 --bundles=tags --max-depth=1
drush devel-generate:content 100 15 --bundles=article --languages=en
drush devel-generate:content 10 --bundles=page --languages=en
drush php:script /opt/scripts/demo-content.php -- after

echo "==> Faking site activity (timeline, page views, system log)"
drush php:script /opt/scripts/demo-activity.php

echo "==> Installing the Neuron AI agents (Concierge and Intelligence)"
drush pm:install neuron_demo -y
if [ ! -f app/web/modules/custom/neuron_demo/ui/dist/chat.js ]; then
  docker compose run --rm node sh -c "npm ci && npm run build"
fi
drush cache:rebuild
docker compose up -d worker

echo
echo "Done! Site: http://localhost:${DRUPAL_PORT}  (login: admin / admin)"
if [ -z "${ANTHROPIC_API_KEY:-}" ]; then
  echo "The AI agents need ANTHROPIC_API_KEY in .env, then: docker compose up -d"
fi
