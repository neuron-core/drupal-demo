<?php

namespace Drupal\neuron_demo\Plugin\NeuronTool;

use Drupal\ai_neuron\Attribute\NeuronTool;
use Drupal\ai_neuron\Tools\NeuronToolPluginBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\ToolProperty;

#[NeuronTool(
  id: 'read_article',
  description: 'Read an article: title, status, author, tags, link and HTML body.',
)]
final class ReadArticleNeuronTool extends NeuronToolPluginBase {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  protected function properties(): array {
    return [
      new ToolProperty('id', PropertyType::INTEGER, 'The article id.', TRUE),
    ];
  }

  public function __invoke(int $id): string {
    $article = $this->entityTypeManager->getStorage('node')->load($id);

    return json_encode([
      'id' => $id,
      'title' => $article->label(),
      'status' => $article->moderation_state->value,
      'author' => $article->getOwner()->getDisplayName(),
      'date' => date('Y-m-d', $article->getCreatedTime()),
      'tags' => array_map(fn ($tag) => $tag->label(), $article->field_tags->referencedEntities()),
      'link' => $article->toUrl()->toString(),
      'body' => $article->field_body->value,
    ]);
  }

}
