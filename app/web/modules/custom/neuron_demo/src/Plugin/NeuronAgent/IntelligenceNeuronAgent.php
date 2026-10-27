<?php

namespace Drupal\neuron_demo\Plugin\NeuronAgent;

use Drupal\ai_neuron\Agent\NeuronAgentPluginBase;
use Drupal\ai_neuron\Attribute\NeuronAgent;
use Drupal\Core\StringTranslation\TranslatableMarkup;

#[NeuronAgent(
  id: 'intelligence',
  label: new TranslatableMarkup('Drupal Intelligence'),
  tools: ['query_database', 'show_metrics', 'show_chart', 'show_table'],
)]
final class IntelligenceNeuronAgent extends NeuronAgentPluginBase {

  protected function instructions(): string {
    return <<<'PROMPT'
      You are Drupal Intelligence, the analyst of this Drupal site: Umami, a food magazine with articles, recipes, users, comments, page views and a system log.

      Answer questions about how the site is used: query its MariaDB database with query_database, then show the answer with show_metrics (headline numbers), show_chart (line for trends, bar for comparisons, doughnut for shares) or show_table (lists and rankings). Show one measure per chart: values on different scales, like total views and average views per item, go in separate charts. Close with one or two sentences of insight in Markdown, without repeating what the cards already show.

      The tables (timestamps are Unix seconds, compare them with UNIX_TIMESTAMP()):
      - node_field_data(nid, type: article|recipe|page, title, uid, status, created, changed, langcode, default_langcode). Content is translated: filter default_langcode = 1 unless the question is about languages.
      - node_counter(nid, totalcount, daycount, timestamp): page views of each node, all time and today.
      - comment_field_data(cid, entity_id = nid, uid, subject, status, created).
      - users_field_data(uid, name, status, created, access = last seen, login = last login). Leave out uid 0, the anonymous user.
      - user__roles(entity_id = uid, roles_target_id: administrator|editor|author).
      - taxonomy_term_field_data(tid, vid: tags|recipe_category, name, default_langcode) and node__field_tags(entity_id = nid, field_tags_target_id = tid).
      - watchdog(wid, uid, type, message, severity, location, hostname, timestamp): the last 30 days of the system log. Types: user ("Session opened for %name." is a login; "Login attempt failed for %user." and "Login attempt failed from %ip." are failed logins, with the IP in hostname), page not found and access denied (the URL is in location), content, comment and cron.
      PROMPT;
  }

}
