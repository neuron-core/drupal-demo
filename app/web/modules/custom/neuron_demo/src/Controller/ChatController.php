<?php

namespace Drupal\neuron_demo\Controller;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\neuron_demo\AgentRun;
use NeuronAI\Agent\Adapters\AGUIAdapter;
use NeuronAI\Chat\History\MessageStoreInterface;
use NeuronAI\Workflow\Persistence\PersistenceInterface;
use NeuronAI\Workflow\WorkflowEngine;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * The chat pages, the endpoint that queues a chat turn, and the conversations.
 */
class ChatController extends ControllerBase {

  public function __construct(
    protected MessageBusInterface $bus,
    protected MessageStoreInterface $messages,
    protected PersistenceInterface $persistence,
  ) {}

  public function page(string $agent): array {
    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['id' => 'neuron-demo'],
      '#attached' => [
        'library' => ['neuron_demo/chat'],
        'drupalSettings' => [
          'neuronDemo' => [
            'agent' => $agent,
            'hub' => getenv('MERCURE_PUBLIC_URL'),
            'run' => Url::fromRoute('neuron_demo.run', ['agent' => $agent])->toString(),
            'threads' => Url::fromRoute('neuron_demo.threads', ['agent' => $agent])->toString(),
          ],
        ],
      ],
    ];
  }

  public function run(string $agent, Request $request): JsonResponse {
    $this->bus->dispatch(new AgentRun($agent, $request->toArray()), [new TransportNamesStamp('asynchronous')]);
    return new JsonResponse(NULL, 202);
  }

  /**
   * The agent's latest conversations, titled by their first message.
   */
  public function threads(string $agent): JsonResponse {
    $threads = $this->entityTypeManager()->getStorage('neuron_message')->getAggregateQuery()
      ->accessCheck(FALSE)
      ->condition('agent_id', $agent)
      ->groupBy('thread_id')
      ->aggregate('created', 'MAX')
      ->sortAggregate('created', 'MAX', 'DESC')
      ->range(0, 20)
      ->execute();

    return new JsonResponse(array_map(fn (array $thread) => [
      'id' => substr($thread['thread_id'], strlen($agent) + 1),
      'title' => Unicode::truncate($this->messages->loadAll($thread['thread_id'])[0]->getContent() ?? '', 60, TRUE, TRUE),
      'updated' => (int) $thread['created_max'],
    ], $threads));
  }

  /**
   * A conversation as AG-UI messages, with the approval it waits for.
   */
  public function thread(string $agent, string $thread): JsonResponse {
    $id = "$agent.$thread";
    $run = (new WorkflowEngine($this->persistence))->inspect($id);

    return new JsonResponse((new AGUIAdapter($thread))->hydrate($this->messages->loadAll($id), $run));
  }

}
