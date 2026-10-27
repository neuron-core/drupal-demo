<?php

namespace Drupal\neuron_demo\Plugin\NeuronTool;

use Drupal\ai_neuron\Attribute\NeuronTool;
use Drupal\ai_neuron\Tools\NeuronToolPluginBase;
use NeuronAI\Tools\ArrayProperty;
use NeuronAI\Tools\ObjectProperty;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\ToolProperty;

#[NeuronTool(
  id: 'show_chart',
  description: 'Show the user an interactive chart: bar or line for comparisons and trends, doughnut for shares of a whole.',
)]
final class ShowChartNeuronTool extends NeuronToolPluginBase {

  protected function properties(): array {
    return [
      new ToolProperty('title', PropertyType::STRING, 'A short heading.', TRUE),
      new ToolProperty('type', PropertyType::STRING, 'The kind of chart.', TRUE, ['bar', 'line', 'doughnut']),
      new ArrayProperty('labels', 'The categories, or the dates of a trend.', TRUE, new ToolProperty('label', PropertyType::STRING)),
      new ArrayProperty('series', 'One or more series of values, one value per label.', TRUE, new ObjectProperty('serie', properties: [
        new ToolProperty('name', PropertyType::STRING, 'The series name.', TRUE),
        new ArrayProperty('values', 'The values.', TRUE, new ToolProperty('value', PropertyType::NUMBER)),
      ])),
    ];
  }

  public function __invoke(string $title, string $type, array $labels, array $series): string {
    return 'The chart is on screen.';
  }

}
