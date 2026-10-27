<?php

namespace Drupal\neuron_demo\Plugin\NeuronTool;

use Drupal\ai_neuron\Attribute\NeuronTool;
use Drupal\ai_neuron\Tools\NeuronToolPluginBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\ToolProperty;

#[NeuronTool(
  id: 'find_articles',
  description: 'List articles, newest first, optionally only those whose title contains some words.',
)]
final class FindArticlesNeuronTool extends NeuronToolPluginBase {

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
      new ToolProperty('search', PropertyType::STRING, 'Words the title must contain.'),
    ];
  }

  public function __invoke(?string $search = NULL): string {
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()->accessCheck(FALSE)
      ->condition('type', 'article')
      ->sort('created', 'DESC')
      ->range(0, 30);
    if ($search) {
      $query->condition('title', $search, 'CONTAINS');
    }

    return json_encode(array_values(array_map(fn (NodeInterface $article) => [
      'id' => (int) $article->id(),
      'title' => $article->label(),
      'status' => $article->moderation_state->value,
      'author' => $article->getOwner()->getDisplayName(),
      'date' => date('Y-m-d', $article->getCreatedTime()),
    ], $storage->loadMultiple($query->execute()))));
  }

}
