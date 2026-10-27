<script setup>
import { useAgent } from '@copilotkit/vue'
import { computed, ref, watch } from 'vue'

// The open conversation ({ id, existing }), and the agent's ones stored in Drupal.
const conversation = defineModel({ type: Object })
const { agent } = defineProps({ agent: String })
const threads = ref([])

async function load() {
  threads.value = await (await fetch(window.drupalSettings.neuronDemo.threads)).json()
}
load()

// Reload the list after every run, so a new conversation shows up with its title.
const { agent: copilotAgent } = useAgent({ agentId: agent })
watch(copilotAgent, (current, _previous, onCleanup) => {
  const subscription = current.subscribe({ onRunFinalized: load })
  onCleanup(() => subscription.unsubscribe())
}, { immediate: true })

const selected = computed({
  get: () => conversation.value.id,
  set: (id) => (conversation.value = { id, existing: true }),
})
const newConversation = () => (conversation.value = { id: crypto.randomUUID(), existing: false })
const when = (time) => new Date(time * 1000).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })
</script>

<template>
  <div class="nd-threads">
    <select v-model="selected" aria-label="Conversation">
      <option v-if="!threads.some((thread) => thread.id === selected)" :value="selected">New conversation</option>
      <option v-for="thread in threads" :key="thread.id" :value="thread.id">{{ thread.title }} · {{ when(thread.updated) }}</option>
    </select>
    <button type="button" class="nd-button" @click="newConversation">New conversation</button>
  </div>
</template>
