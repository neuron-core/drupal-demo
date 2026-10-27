<?php

namespace Drupal\neuron_demo\Plugin\NeuronTool;

use Drupal\ai_neuron\Attribute\NeuronTool;
use Drupal\ai_neuron\Tools\NeuronToolPluginBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\ToolInterface;
use NeuronAI\Tools\ToolProperty;

#[NeuronTool(
  id: 'update_article',
  description: 'Save a new title and/or HTML body for an existing article.',
)]
final class UpdateArticleNeuronTool extends NeuronToolPluginBase {

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
      new ToolProperty('id', PropertyType::INTEGER, 'The article id.', TRUE),
      new ToolProperty('title', PropertyType::STRING, 'The new title, when it changes.'),
      new ToolProperty('body', PropertyType::STRING, 'The complete new body in HTML, when it changes.'),
    ];
  }

  public function __invoke(int $id, ?string $title = NULL, ?string $body = NULL): string {
    $article = $this->entityTypeManager->getStorage('node')->load($id);
    if ($title) {
      $article->setTitle($title);
    }
    if ($body) {
      $article->field_body->value = $body;
    }
    $article->setNewRevision();
    $article->setRevisionCreationTime(time());
    $article->setRevisionLogMessage('Edited by the Drupal Concierge.');
    $article->save();

    return "Article $id saved.";
  }

}
