<script setup>
defineProps({ title: String, metrics: Array })
</script>

<template>
  <section class="nd-card">
    <h3 class="nd-card__title">{{ title }}</h3>
    <div class="nd-metrics">
      <div v-for="metric in metrics" :key="metric.label" class="nd-metric">
        <div class="nd-metric__label">{{ metric.label }}</div>
        <div class="nd-metric__value">{{ metric.value }}</div>
        <div v-if="metric.detail" class="nd-metric__detail">{{ metric.detail }}</div>
      </div>
    </div>
  </section>
</template>
