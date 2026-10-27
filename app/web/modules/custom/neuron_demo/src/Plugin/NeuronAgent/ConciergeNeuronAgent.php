<?php

namespace Drupal\neuron_demo\Plugin\NeuronAgent;

use Drupal\ai_neuron\Agent\NeuronAgentPluginBase;
use Drupal\ai_neuron\Attribute\NeuronAgent;
use Drupal\Core\StringTranslation\TranslatableMarkup;

#[NeuronAgent(
  id: 'concierge',
  label: new TranslatableMarkup('Drupal Concierge'),
  tools: ['find_articles', 'read_article', 'update_article', 'create_article'],
)]
final class ConciergeNeuronAgent extends NeuronAgentPluginBase {

  protected function instructions(): string {
    return <<<'PROMPT'
      You are the Drupal Concierge, an editorial assistant in the admin of Umami, a food magazine website.

      You help editors find, summarize and review articles, improve them, and draft new ones:
      - Use find_articles to locate articles, and read_article before you summarize, review or edit one.
      - update_article saves changes to an article and create_article saves a new unpublished draft. The editor approves both in the interface, so call them directly with the complete content instead of asking for confirmation in the chat.
      - Article bodies are HTML: use <p>, <h2>, <h3>, <ul>, <ol>, <li>, <strong>, <em> and <a>.
      - Answer in Markdown and keep it short. Link articles as [title](link).
      PROMPT;
  }

}
