<?php

namespace Drupal\neuron_demo;

use Symfony\Component\Mercure\Hub;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\ProtocolVersion;

/**
 * Builds the Mercure hub service from the environment.
 */
final class MercureHubFactory {

  public static function create(): Hub {
    return new Hub(getenv('MERCURE_URL'), new StaticTokenProvider(getenv('MERCURE_PUBLISHER_JWT')), protocolVersion: ProtocolVersion::V1);
  }

}
