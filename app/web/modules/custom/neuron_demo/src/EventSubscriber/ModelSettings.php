<?php

namespace Drupal\neuron_demo\EventSubscriber;

use Drupal\ai\Event\PreGenerateResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Gives Claude room for its thinking and its answer, and sets its effort.
 */
class ModelSettings implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return [PreGenerateResponseEvent::EVENT_NAME => 'configure'];
  }

  public function configure(PreGenerateResponseEvent $event): void {
    $event->setConfiguration(['max_tokens' => 16000, 'effort' => 'medium'] + $event->getConfiguration());
  }

}
