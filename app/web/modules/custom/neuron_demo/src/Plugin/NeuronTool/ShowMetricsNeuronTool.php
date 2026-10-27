<?php

namespace Drupal\neuron_demo\Plugin\NeuronTool;

use Drupal\ai_neuron\Attribute\NeuronTool;
use Drupal\ai_neuron\Tools\NeuronToolPluginBase;
use NeuronAI\Tools\ArrayProperty;
use NeuronAI\Tools\ObjectProperty;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\ToolProperty;

#[NeuronTool(
  id: 'show_metrics',
  description: 'Show key numbers to the user as a row of metric cards.',
)]
final class ShowMetricsNeuronTool extends NeuronToolPluginBase {

  protected function properties(): array {
    return [
      new ToolProperty('title', PropertyType::STRING, 'A short heading.', TRUE),
      new ArrayProperty('metrics', 'Two to six metrics.', TRUE, new ObjectProperty('metric', properties: [
        new ToolProperty('label', PropertyType::STRING, 'What is measured.', TRUE),
        new ToolProperty('value', PropertyType::STRING, 'The formatted value, like "1,284" or "12%".', TRUE),
        new ToolProperty('detail', PropertyType::STRING, 'Optional context, like "+8% on last week".'),
      ])),
    ];
  }

  public function __invoke(string $title, array $metrics): string {
    return 'The metric cards are on screen.';
  }

}
