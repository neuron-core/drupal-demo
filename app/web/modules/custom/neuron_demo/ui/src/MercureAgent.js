import { AbstractAgent } from '@ag-ui/client'
import { EventSchemas } from '@ag-ui/core/schemas'
import { createProtocolStream, subscribeToMercure } from '@neuron-core/streaming'
import { Observable } from 'rxjs'

const { hub, run, threads } = window.drupalSettings.neuronDemo

// An AG-UI agent whose runs execute in the Drupal worker: it listens on the
// run's Mercure topic, posts the input to Drupal, and replays the AG-UI events
// the worker publishes until the run ends.
export class MercureAgent extends AbstractAgent {
  // CopilotChat calls this when it opens a conversation: replay it from Drupal,
  // including the approval it may be waiting for.
  connect(input) {
    return new Observable((observer) => {
      fetch(`${threads}/${input.threadId}`)
        .then((response) => response.json())
        .then(({ messages, interrupts }) => {
          const ids = { threadId: input.threadId, runId: input.runId }
          observer.next({ type: 'RUN_STARTED', ...ids })
          observer.next({ type: 'MESSAGES_SNAPSHOT', messages })
          observer.next({ type: 'RUN_FINISHED', ...ids, ...(interrupts.length ? { outcome: { type: 'interrupt', interrupts } } : {}) })
          observer.complete()
        })
        .catch((error) => observer.error(error))
    })
  }

  run(input) {
    return new Observable((observer) => {
      const stream = createProtocolStream((callbacks) => listen(input, callbacks), (event) => EventSchemas.parse(event))
      const reader = stream.getReader()
      ;(async () => {
        try {
          for (let next = await reader.read(); !next.done; next = await reader.read()) {
            observer.next(next.value)
          }
          observer.complete()
        } catch (error) {
          observer.error(error)
        }
      })()
      return () => reader.cancel().catch(() => {})
    })
  }
}

function listen(input, callbacks) {
  const url = new URL(hub)
  url.searchParams.append('match', `neuron-demo/runs/${input.runId}`)
  const source = new EventSource(url)
  const subscription = subscribeToMercure(source, callbacks)

  // Start the run only once the subscription is open, so no event is missed.
  source.addEventListener('open', () => {
    fetch(run, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(input) })
      .then((response) => response.ok || callbacks.onGap(`The run was refused: ${response.status}`))
  }, { once: true })

  return {
    close() {
      subscription.close()
      source.close()
    },
  }
}
