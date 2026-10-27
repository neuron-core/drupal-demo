<?php

namespace Drupal\neuron_demo\Messenger;

use Drupal\ai_neuron\Agent\NeuronAgentManagerInterface;
use Drupal\neuron_demo\AgentRun;
use NeuronAI\Agent\Adapters\AGUIAdapter;
use NeuronAI\Agent\Frontend\AGUIInputTranslator;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Workflow\Streaming\Channel\MercureChannel;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * Runs a chat turn and streams its AG-UI events to the browser via Mercure.
 */
#[AsMessageHandler]
class AgentRunHandler {

  public function __construct(
    protected NeuronAgentManagerInterface $agents,
    protected HubInterface $hub,
  ) {}

  public function __invoke(AgentRun $run): void {
    $input = $run->input;

    $agent = $this->agents->createAgent($run->agent, ['thread_key' => $input['threadId']])
      ->setStreamAdapter(fn () => new AGUIAdapter($input['threadId'], $input['runId'], $input['messages'], $input['state'] ?? []))
      ->setChannel(fn () => new MercureChannel($this->hub, 'neuron-demo/runs/' . $input['runId'], private: FALSE));

    try {
      if (empty($input['resume'])) {
        $agent->chat(new UserMessage(end($input['messages'])['content']), stream: TRUE);
      }
      else {
        // The editor answered an approval: resume the suspended run.
        $agent->submitInputs($input, new AGUIInputTranslator())->run();
      }
    }
    catch (\Throwable $e) {
      // The browser got the error through the stream: a turn is not retried.
      throw new UnrecoverableMessageHandlingException($e->getMessage(), 0, $e);
    }
  }

}
