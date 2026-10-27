<?php

namespace Drupal\neuron_demo\Plugin\NeuronTool;

use Drupal\ai_neuron\Attribute\NeuronTool;
use Drupal\ai_neuron\Tools\NeuronToolPluginBase;
use Drupal\Core\Database\Connection;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\ToolProperty;

#[NeuronTool(
  id: 'query_database',
  description: 'Run one read-only SQL query (MariaDB) on the Drupal database and get the rows as JSON.',
)]
final class QueryDatabaseNeuronTool extends NeuronToolPluginBase {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected Connection $database,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  protected function properties(): array {
    return [
      new ToolProperty('sql', PropertyType::STRING, 'A single SELECT statement, without a trailing semicolon.', TRUE),
    ];
  }

  public function __invoke(string $sql): string {
    $rows = $this->database->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    return json_encode(array_slice($rows, 0, 200), JSON_INVALID_UTF8_SUBSTITUTE);
  }

}
