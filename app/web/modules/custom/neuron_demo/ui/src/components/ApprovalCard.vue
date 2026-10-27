<script setup>
import { ref } from 'vue'

// The tool call Neuron suspended on: its name and the inputs to approve.
const { action } = defineProps({ action: Object })
const emit = defineEmits(['decide'])
const { id, title, body } = action.inputs
const decided = ref(false)

function decide(decision) {
  decided.value = true
  emit('decide', decision)
}
</script>

<template>
  <section class="nd-card nd-approval">
    <p class="nd-approval__badge">Approval needed</p>
    <h3 class="nd-card__title">{{ action.name === 'create_article' ? 'Save a new draft' : `Update article #${id}` }}</h3>
    <dl class="nd-approval__fields">
      <template v-if="title">
        <dt>Title</dt>
        <dd>{{ title }}</dd>
      </template>
      <template v-if="body">
        <dt>Body</dt>
        <dd class="nd-approval__body" v-html="body" />
      </template>
    </dl>
    <div class="nd-approval__actions">
      <button type="button" class="nd-button nd-button--primary" :disabled="decided" @click="decide({ approved: true })">Approve</button>
      <button type="button" class="nd-button" :disabled="decided" @click="decide({ approved: false, reason: 'The editor rejected this change' })">Reject</button>
    </div>
  </section>
</template>
