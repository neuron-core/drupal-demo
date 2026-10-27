<?php

namespace Drupal\neuron_demo\Plugin\NeuronTool;

use Drupal\ai_neuron\Attribute\NeuronTool;
use Drupal\ai_neuron\Tools\NeuronToolPluginBase;
use NeuronAI\Tools\ArrayProperty;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\ToolProperty;

#[NeuronTool(
  id: 'show_table',
  description: 'Show the user a sortable table.',
)]
final class ShowTableNeuronTool extends NeuronToolPluginBase {

  protected function properties(): array {
    return [
      new ToolProperty('title', PropertyType::STRING, 'A short heading.', TRUE),
      new ArrayProperty('columns', 'The column names.', TRUE, new ToolProperty('column', PropertyType::STRING)),
      new ArrayProperty('rows', 'The rows, one cell per column.', TRUE, new ArrayProperty('row', items: new ToolProperty('cell', PropertyType::STRING))),
    ];
  }

  public function __invoke(string $title, array $columns, array $rows): string {
    return 'The table is on screen.';
  }

}
