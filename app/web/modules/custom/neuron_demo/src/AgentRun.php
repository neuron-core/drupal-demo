<?php

namespace Drupal\neuron_demo;

/**
 * A chat turn to run in the background, with the browser's AG-UI input.
 */
final class AgentRun {

  public function __construct(
    public readonly string $agent,
    public readonly array $input,
  ) {}

}
