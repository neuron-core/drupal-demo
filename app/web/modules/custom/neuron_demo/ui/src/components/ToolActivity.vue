<script setup>
import { computed } from 'vue'

const { name, args, status, result } = defineProps({ name: String, args: Object, status: String, result: String })

const labels = {
  find_articles: ({ search }) => (search ? `Searching articles for “${search}”` : 'Listing the latest articles'),
  read_article: ({ id }) => `Reading article #${id}`,
  update_article: ({ id }) => `Editing article #${id}`,
  create_article: ({ title }) => `Drafting “${title}”`,
  query_database: () => 'Querying the database',
}

// Neuron answers a rejected approval with this instruction instead of running the tool.
const rejected = computed(() => result?.startsWith('TOOL NOT EXECUTED'))
</script>

<template>
  <div class="nd-activity" :class="{ 'is-running': status !== 'complete', 'is-rejected': rejected }">
    <span class="nd-activity__icon" aria-hidden="true">{{ status !== 'complete' ? '' : rejected ? '✕' : '✓' }}</span>
    <span>{{ labels[name]?.(args ?? {}) ?? name }}{{ rejected ? ' (rejected)' : '' }}</span>
    <details v-if="args?.sql" class="nd-activity__sql">
      <summary>SQL</summary>
      <pre>{{ args.sql }}</pre>
    </details>
  </div>
</template>
