<?php

/**
 * @file
 * Fakes realistic site activity on top of the generated demo content.
 *
 * Usage: drush php:script scripts/demo-activity.php
 *
 * 1. Timeline: spreads content over ~2 years and comments after their article,
 *    then backdates user accounts and last login/access to match.
 * 2. Page views: fills the Statistics module's node_counter table.
 * 3. System log: writes 30 days of backdated dblog entries (logins, failed
 *    logins, content changes, comments, 404/403 errors, cron runs).
 *
 * Randomness is seeded, so every rebuild produces the same story.
 */

use Drupal\Core\Link;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\user\Entity\Role;

mt_srand(20261019);

const DAY = 86400;
$now = \Drupal::time()->getRequestTime();
$db = \Drupal::database();
$etm = \Drupal::entityTypeManager();
$base_url = 'http://localhost:8080';

$rand = fn(float $min, float $max): float => $min + (mt_rand() / mt_getrandmax()) * ($max - $min);
$chance = fn(float $p): bool => mt_rand() / mt_getrandmax() < $p;
// Log-normal sample: most values near exp($mu), with a long tail.
$lognormal = function (float $mu, float $sigma) use ($rand): float {
  $gauss = sqrt(-2 * log($rand(1e-9, 1))) * cos(2 * M_PI * $rand(0, 1));
  return exp($mu + $sigma * $gauss);
};
// Fake IPs from the RFC 5737 documentation ranges.
$fake_ip = fn(): string => ['192.0.2.', '198.51.100.', '203.0.113.'][mt_rand(0, 2)] . mt_rand(2, 254);

// ---------------------------------------------------------------------------
// 1. Timeline.
// ---------------------------------------------------------------------------

// Users first: when each one joined and when they were last seen. Content
// and comments are then attributed only to users who were around at the time.
$users = $db->query("SELECT uid, name, mail FROM {users_field_data} WHERE uid > 0")->fetchAllAssoc('uid');
$roles = $db->query("SELECT entity_id, roles_target_id FROM {user__roles}")->fetchAllKeyed();
$user_ip = [];
foreach ($users as $uid => $user) {
  $user_ip[$uid] = $fake_ip();
  if ($uid == 1) {
    $created = $now - 760 * DAY;
    $access = $now - mt_rand(60, 1800);
  }
  elseif (isset($roles[$uid])) {
    // Editors and authors are regulars who joined early on.
    $created = $now - mt_rand(60 * DAY, 740 * DAY);
    $access = $now - ($chance(0.75) ? mt_rand(300, 7 * DAY) : mt_rand(7 * DAY, 60 * DAY));
  }
  else {
    // Members range from daily visitors to long gone, and some never came back.
    $created = (int) ($now - 730 * DAY * $rand(0, 1) - mt_rand(3600, DAY));
    $profile = ['week', 'week', 'week', 'week', 'week', 'quarter', 'quarter', 'quarter', 'quarter', 'quarter',
      'quarter', 'dormant', 'dormant', 'dormant', 'dormant', 'dormant', 'never', 'never', 'never'][mt_rand(0, 18)];
    $access = match ($profile) {
      'week' => $now - mt_rand(300, 7 * DAY),
      'quarter' => $now - mt_rand(7 * DAY, 90 * DAY),
      'dormant' => $now - mt_rand(90 * DAY, 500 * DAY),
      'never' => 0,
    };
    $access = $access ? max($access, $created + mt_rand(600, 2 * DAY)) : 0;
  }
  $access = min($access, $now - 60);
  $users[$uid]->created = $created;
  $users[$uid]->access = $access;
  $users[$uid]->login = $access ? max($created, $access - mt_rand(60, 4 * 3600)) : 0;
}
// Users who were members and still active at time $t.
$active_at = function (int $t, bool $staff_only = FALSE) use ($users, $roles): int {
  $eligible = array_filter($users, fn($u) => $u->created < $t && $u->access > $t && (!$staff_only || $u->uid == 1 || isset($roles[$u->uid])));
  return $eligible ? (int) array_rand($eligible) : 1;
};

// Umami's hand-written content is the "established" part of the site; the
// generated articles and pages skew recent (more content published lately).
$nodes = $db->query("SELECT nid, type FROM {node_field_data} WHERE default_langcode = 1")->fetchAllKeyed();
$node_created = [];
foreach ($nodes as $nid => $type) {
  $days_ago = $nid <= 19 ? $rand(400, 700) : 700 * $rand(0, 1) ** 1.5;
  $created = (int) ($now - $days_ago * DAY - mt_rand(0, DAY));
  $changed = $chance(0.25) ? (int) $rand($created, $now - 3600) : $created;
  $uid = $active_at($created, TRUE);
  $node_created[$nid] = $created;
  foreach (['node_field_data', 'node_field_revision'] as $table) {
    $db->update($table)->fields(['uid' => $uid, 'created' => $created, 'changed' => $changed])->condition('nid', $nid)->execute();
  }
  $db->update('node_revision')->fields(['revision_uid' => $uid, 'revision_timestamp' => $changed])->condition('nid', $nid)->execute();
}

// Comments arrive mostly in the days right after an article is published,
// from whichever members were active then.
$comments = $db->query("SELECT cid, entity_id FROM {comment_field_data} WHERE entity_type = 'node'")->fetchAllKeyed();
foreach ($comments as $cid => $nid) {
  $delay = -log($rand(1e-6, 1)) * 10 * DAY;
  $created = (int) min($node_created[$nid] + $delay, $now - mt_rand(60, 6 * 3600));
  $uid = $active_at($created);
  $db->update('comment_field_data')
    ->fields(['uid' => $uid, 'name' => $users[$uid]->name, 'hostname' => $user_ip[$uid], 'created' => $created, 'changed' => $created])
    ->condition('cid', $cid)
    ->execute();
}
$etm->getStorage('node')->resetCache();
$comment_storage = $etm->getStorage('comment');
$comment_storage->resetCache();
foreach (array_unique($comments) as $nid) {
  // Recomputes each article's comment count and "last comment" timestamp.
  \Drupal::service('comment.statistics')->update($comment_storage->load(array_search($nid, $comments)));
}

foreach ($users as $uid => $user) {
  $db->update('users_field_data')
    ->fields(['created' => $user->created, 'changed' => max($user->created, $user->login), 'login' => $user->login, 'access' => $user->access])
    ->condition('uid', $uid)
    ->execute();
}
$etm->getStorage('user')->resetCache();
echo "Timeline: " . count($nodes) . " nodes, " . count($comments) . " comments, " . count($users) . " users backdated.\n";

// ---------------------------------------------------------------------------
// 2. Page views (Statistics module).
// ---------------------------------------------------------------------------

\Drupal::configFactory()->getEditable('statistics.settings')->set('count_content_views', 1)->save();
foreach (['anonymous', 'authenticated'] as $rid) {
  $role = Role::load($rid);
  $role->grantPermission('view post access counter');
  $role->save();
}

$midnight = strtotime('today', $now);
$today_fraction = ($now - $midnight) / DAY;
$db->delete('node_counter')->execute();
$insert = $db->insert('node_counter')->fields(['nid', 'totalcount', 'daycount', 'timestamp']);
foreach ($nodes as $nid => $type) {
  // Typical daily views per content type; Umami's curated content is the
  // most popular. Older content has had more time to collect views.
  $daily = match (TRUE) {
    $type === 'recipe' => $lognormal(log(45), 0.7),
    $type === 'article' && $nid <= 19 => $lognormal(log(30), 0.6),
    $type === 'article' => $lognormal(log(6), 1.1),
    default => $lognormal(log(2), 0.8),
  };
  $age_days = ($now - $node_created[$nid]) / DAY;
  $total = (int) round($daily * (7 + $age_days ** 0.9));
  $today = (int) round($daily * $today_fraction * $rand(0.4, 1.6));
  $last_view = $today > 0
    ? $now - mt_rand(0, (int) min($now - $midnight, DAY / max($daily, 1)))
    : $now - mt_rand((int) ($now - $midnight), (int) (DAY / max($daily, 0.2)) + DAY);
  $insert->values([$nid, max($total, $today, 1), $today, $last_view]);
}
$insert->execute();
// "Today" started at midnight, so the daily counters reset at the next one.
\Drupal::state()->set('statistics.day_timestamp', $midnight);

// Show "Popular content" (today / all time / last viewed) on the listings.
if (!$etm->getStorage('block')->load('umami_popular_content')) {
  $etm->getStorage('block')->create([
    'id' => 'umami_popular_content',
    'theme' => 'umami',
    'region' => 'content_bottom',
    'weight' => 10,
    'plugin' => 'statistics_popular_block',
    'settings' => [
      'id' => 'statistics_popular_block',
      'label' => 'Popular content',
      'label_display' => 'visible',
      'provider' => 'statistics',
      'top_day_num' => 5,
      'top_all_num' => 5,
      'top_last_num' => 5,
    ],
    'visibility' => [
      'request_path' => ['id' => 'request_path', 'pages' => "/articles\n/recipes", 'negate' => FALSE],
    ],
  ])->save();
}
echo "Page views: " . count($nodes) . " nodes, " . number_format((int) $db->query("SELECT SUM(totalcount) FROM {node_counter}")->fetchField()) . " total views.\n";

// ---------------------------------------------------------------------------
// 3. System log (dblog), last 30 days.
// ---------------------------------------------------------------------------

// Keep up to 10k entries, so cron doesn't trim the backdated history.
\Drupal::configFactory()->getEditable('dblog.settings')->set('row_limit', 10000)->save();
// The log screen sorts by entry ID, so start empty rather than leave the
// install messages ("today") below the backdated history.
$db->truncate('watchdog')->execute();

$window_start = $now - 30 * DAY;
$entries = [];
$log = function (string $channel, int $severity, string $message, array $variables, int $timestamp, array $context = []) use (&$entries, $base_url) {
  $entries[] = [$severity, $message, $variables + $context + [
    'channel' => $channel,
    'uid' => 0,
    'link' => '',
    'request_uri' => $base_url . '/',
    'referer' => '',
    'ip' => '127.0.0.1',
    'timestamp' => $timestamp,
  ]];
};

// Logins and logouts, ending exactly at each user's recorded last login.
foreach ($users as $uid => $user) {
  if (!$user->login || $user->access < $window_start) {
    continue;
  }
  $gap = $uid == 1 || isset($roles[$uid]) ? [0.5, 2.5] : [1.5, 9];
  for ($t = $user->login; $t >= max($window_start, $user->created); $t -= (int) ($rand(...$gap) * DAY)) {
    $context = ['uid' => $uid, 'ip' => $user_ip[$uid], 'request_uri' => "$base_url/user/login", 'referer' => "$base_url/"];
    $log('user', RfcLogLevel::INFO, 'Session opened for %name.', ['%name' => $user->name], $t, $context);
    $closed = $t + mt_rand(300, 3 * 3600);
    if ($chance(0.5) && $closed < $user->access) {
      $log('user', RfcLogLevel::INFO, 'Session closed for %name.', ['%name' => $user->name], $closed, ['request_uri' => "$base_url/user/logout"] + $context);
    }
  }
  if ($user->created >= $window_start) {
    $log('user', RfcLogLevel::INFO, 'New user: %name %email.', ['%name' => $user->name, '%email' => '<' . $user->mail . '>'], $user->created, ['ip' => $user_ip[$uid], 'request_uri' => "$base_url/user/register"]);
  }
}

// Failed logins: typos by real users, guesses at common names, and one
// brute-force burst against "admin".
$names = array_column($users, 'name');
for ($t = $window_start; $t < $now; $t += DAY) {
  for ($i = mt_rand(0, 4); $i > 0; $i--) {
    $context = ['request_uri' => "$base_url/user/login", 'timestamp' => $t + mt_rand(0, DAY - 1)];
    if ($chance(0.6)) {
      $log('user', RfcLogLevel::NOTICE, 'Login attempt failed for %user.', ['%user' => $names[array_rand($names)]], $context['timestamp'], ['ip' => $fake_ip()] + $context);
    }
    else {
      $ip = $fake_ip();
      $log('user', RfcLogLevel::NOTICE, 'Login attempt failed from %ip.', ['%ip' => $ip], $context['timestamp'], ['ip' => $ip] + $context);
    }
  }
}
$burst = $now - 9 * DAY - 7200;
for ($i = 0; $i < 30; $i++) {
  $burst += mt_rand(2, 20);
  $user = $i % 3 ? 'admin' : ['root', 'administrator', 'test', 'webmaster'][mt_rand(0, 3)];
  $user === 'admin'
    ? $log('user', RfcLogLevel::NOTICE, 'Login attempt failed for %user.', ['%user' => $user], $burst, ['ip' => '198.51.100.66', 'request_uri' => "$base_url/user/login"])
    : $log('user', RfcLogLevel::NOTICE, 'Login attempt failed from %ip.', ['%ip' => '198.51.100.66'], $burst, ['ip' => '198.51.100.66', 'request_uri' => "$base_url/user/login"]);
}

// Content and comment activity that happened inside the window.
$node_storage = $etm->getStorage('node');
foreach ($node_storage->loadMultiple(array_keys(array_filter($node_created, fn($c) => $c >= $window_start))) as $node) {
  $uid = $node->getOwnerId();
  $context = ['uid' => $uid, 'ip' => $user_ip[$uid] ?? $fake_ip(), 'link' => Link::fromTextAndUrl(t('View'), $node->toUrl())->toString()];
  $variables = ['@type' => $node->bundle(), '%title' => $node->label()];
  $log('content', RfcLogLevel::INFO, '@type: added %title.', $variables, $node->getCreatedTime(), ['request_uri' => "$base_url/node/add/" . $node->bundle()] + $context);
  if ($node->getChangedTime() > $node->getCreatedTime()) {
    $log('content', RfcLogLevel::INFO, '@type: updated %title.', $variables, $node->getChangedTime(), ['request_uri' => "$base_url/node/{$node->id()}/edit"] + $context);
  }
}
$recent = $comment_storage->getQuery()->accessCheck(FALSE)->condition('created', $window_start, '>=')->execute();
foreach ($comment_storage->loadMultiple($recent) as $comment) {
  $uid = $comment->getOwnerId();
  $log('comment', RfcLogLevel::INFO, 'Comment posted: %subject.', ['%subject' => $comment->getSubject()], $comment->getCreatedTime(), [
    'uid' => $uid,
    'ip' => $user_ip[$uid] ?? $fake_ip(),
    'request_uri' => "$base_url/comment/reply/node/{$comment->getCommentedEntityId()}/comment",
    'link' => Link::fromTextAndUrl(t('View'), $comment->toUrl()->setOption('fragment', 'comment-' . $comment->id()))->toString(),
  ]);
}

// 404s from vulnerability scanners, stale links and missing icons, plus 403s
// from visitors poking at admin pages.
$not_found = [
  '/wp-login.php' => 12, '/wp-admin/' => 8, '/.env' => 8, '/xmlrpc.php' => 5, '/.git/config' => 4,
  '/phpmyadmin/' => 3, '/apple-touch-icon.png' => 10, '/apple-touch-icon-precomposed.png' => 6,
  '/favicon.png' => 3, '/en/blog' => 4, '/en/articles/feed' => 3, '/en/recipes/chocolate-cake' => 4,
  '/sitemap.xml' => 6, '/es/recetas/tarta-de-chocolate' => 2, '/en/node/9999' => 1, '/user/register/admin' => 1,
];
$referers = ['', '', '', 'https://www.google.com/', 'https://duckduckgo.com/', "$base_url/en/articles"];
$denied = ['/admin' => 5, '/admin/content' => 3, '/node/add/article' => 2, '/admin/config' => 2, '/user/2/edit' => 1];
$pick = function (array $weighted): string {
  $n = mt_rand(1, array_sum($weighted));
  foreach ($weighted as $value => $weight) {
    if (($n -= $weight) <= 0) {
      return $value;
    }
  }
};
for ($t = $window_start; $t < $now; $t += DAY) {
  for ($i = mt_rand(5, 25); $i > 0; $i--) {
    $uri = $pick($not_found);
    $log('page not found', RfcLogLevel::WARNING, '@uri', ['@uri' => $uri], min($t + mt_rand(0, DAY - 1), $now), ['ip' => $fake_ip(), 'request_uri' => $base_url . $uri, 'referer' => $referers[array_rand($referers)]]);
  }
  for ($i = mt_rand(0, 4); $i > 0; $i--) {
    $uri = $pick($denied);
    $log('access denied', RfcLogLevel::WARNING, 'Path: @uri. %type: @message in %function (line %line of %file).', [
      '@uri' => $uri,
      '%type' => 'Drupal\Core\Http\Exception\CacheableAccessDeniedHttpException',
      '@message' => "The 'access administration pages' permission is required.",
      '%function' => 'Drupal\Core\Routing\AccessAwareRouter->checkAccess()',
      '%line' => 114,
      '%file' => '/opt/drupal/web/core/lib/Drupal/Core/Routing/AccessAwareRouter.php',
    ], min($t + mt_rand(0, DAY - 1), $now), ['ip' => $fake_ip(), 'request_uri' => $base_url . $uri]);
  }
}

// Automated cron runs roughly every three hours, triggered by page visits.
for ($t = $window_start + mt_rand(0, 3600); $t < $now; $t += 3 * 3600 + mt_rand(0, 1800)) {
  $log('cron', RfcLogLevel::INFO, 'Cron run completed.', [], $t, ['request_uri' => "$base_url/en/articles", 'ip' => $fake_ip()]);
}

// Insert in chronological order, so log IDs increase with time like real logs.
usort($entries, fn($a, $b) => $a[2]['timestamp'] <=> $b[2]['timestamp']);
$dblog = \Drupal::service('logger.dblog');
foreach ($entries as [$severity, $message, $context]) {
  $dblog->log($severity, $message, $context);
}
echo "System log: " . count($entries) . " backdated entries written.\n";
