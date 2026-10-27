<script setup>
import { CopilotChat, CopilotChatConfigurationProvider, useConfigureSuggestions, useInterrupt } from '@copilotkit/vue'
import { ref, watchEffect } from 'vue'
import ApprovalCard from './components/ApprovalCard.vue'
import ChartCard from './components/ChartCard.vue'
import MetricCards from './components/MetricCards.vue'
import TableCard from './components/TableCard.vue'
import ThreadPicker from './components/ThreadPicker.vue'
import ToolActivity from './components/ToolActivity.vue'

const { agent } = defineProps({ agent: String })

// The open conversation lives in the URL, so a page refresh reopens it. An
// existing one is replayed from Drupal; a new one shows the welcome screen.
const fromUrl = new URLSearchParams(location.search).get('thread')
const conversation = ref({ id: fromUrl ?? crypto.randomUUID(), existing: !!fromUrl })
watchEffect(() => history.replaceState(null, '', `?thread=${conversation.value.id}`))

const chats = {
  concierge: {
    labels: {
      welcomeMessageText: "Hi, I'm the Drupal Concierge. I can summarize and review your articles, improve them, or draft new ones.",
      chatInputPlaceholder: 'Ask about your articles…',
    },
    suggestions: [
      { title: 'Latest articles', message: 'What are the five latest articles? Summarize each in one line.' },
      { title: 'Review an article', message: 'Review the article about milk chocolate and suggest improvements.' },
      { title: 'Draft an article', message: 'Draft a short article with five tips for growing herbs on a balcony.' },
    ],
  },
  intelligence: {
    labels: {
      welcomeMessageText: "Hi, I'm Drupal Intelligence. Ask me anything about how this site is used.",
      chatInputPlaceholder: 'Ask about traffic, content, users or the logs…',
    },
    suggestions: [
      { title: 'Site overview', message: 'Give me an overview of the site: content, users, comments and page views.' },
      { title: 'Most popular content', message: 'Which ten articles and recipes have the most page views?' },
      { title: 'User activity', message: 'How active are our users? Show when they were last seen.' },
      { title: 'Security check', message: 'Any suspicious activity in the logs over the last 30 days?' },
    ],
  },
}

useInterrupt({ agentId: agent })
useConfigureSuggestions({ consumerAgentId: agent, suggestions: chats[agent].suggestions })

// CopilotKit reports a failed run without showing it: say so above the chat.
const failed = ref(false)
</script>

<template>
  <div class="nd-chat">
    <ThreadPicker v-model="conversation" :agent="agent" />
    <p v-if="failed" class="nd-error" role="alert">
      The agent could not answer. Check that ANTHROPIC_API_KEY is set, and the worker log: <code>docker compose logs worker</code>
    </p>
    <div class="nd-chat__body">
      <CopilotChatConfigurationProvider :thread-id="conversation.id" :has-explicit-thread-id="conversation.existing">
        <CopilotChat
          :agent-id="agent"
          :labels="chats[agent].labels"
          :on-error="() => (failed = true)"
          @submit-message="failed = false"
        >
          <template #tool-call-show_metrics="{ args, status }">
            <MetricCards v-if="status !== 'inProgress'" v-bind="args" />
          </template>
          <template #tool-call-show_chart="{ args, status }">
            <ChartCard v-if="status !== 'inProgress'" v-bind="args" />
          </template>
          <template #tool-call-show_table="{ args, status }">
            <TableCard v-if="status !== 'inProgress'" v-bind="args" />
          </template>
          <template #tool-call="{ name, args, status, result }">
            <ToolActivity :name="name" :args="args" :status="status" :result="result" />
          </template>
          <template #interrupt="{ interrupt, resolve }">
            <ApprovalCard :action="interrupt.metadata" @decide="resolve" />
          </template>
        </CopilotChat>
      </CopilotChatConfigurationProvider>
    </div>
  </div>
</template>
