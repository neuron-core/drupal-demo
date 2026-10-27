<?php

namespace Drupal\neuron_demo\Plugin\NeuronTool;

use Drupal\ai_neuron\Attribute\NeuronTool;
use Drupal\ai_neuron\Tools\NeuronToolPluginBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\ToolInterface;
use NeuronAI\Tools\ToolProperty;

#[NeuronTool(
  id: 'create_article',
  description: 'Save a new article as an unpublished draft.',
)]
final class CreateArticleNeuronTool extends NeuronToolPluginBase {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public function getNeuron(): ToolInterface {
    return parent::getNeuron()->requireApproval();
  }

  protected function properties(): array {
    return [
      new ToolProperty('title', PropertyType::STRING, 'The title.', TRUE),
      new ToolProperty('body', PropertyType::STRING, 'The body in HTML.', TRUE),
    ];
  }

  public function __invoke(string $title, string $body): string {
    $article = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'article',
      'title' => $title,
      'field_body' => ['value' => $body, 'format' => 'basic_html'],
      'moderation_state' => 'draft',
      'uid' => 1,
    ]);
    $article->save();

    return json_encode(['id' => (int) $article->id(), 'edit' => $article->toUrl('edit-form')->toString()]);
  }

}
