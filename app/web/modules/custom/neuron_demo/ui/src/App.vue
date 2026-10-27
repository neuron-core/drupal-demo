<script setup>
import { CopilotKitProvider } from '@copilotkit/vue'
import Chat from './Chat.vue'
import { MercureAgent } from './MercureAgent.js'

const { agent } = defineProps({ agent: String })

// No CopilotKit runtime: the agent talks to Drupal and Mercure directly.
const agents = { [agent]: new MercureAgent({ agentId: agent }) }
</script>

<template>
  <CopilotKitProvider :self-managed-agents="agents" :enable-inspector="false">
    <Chat :agent="agent" />
  </CopilotKitProvider>
</template>
