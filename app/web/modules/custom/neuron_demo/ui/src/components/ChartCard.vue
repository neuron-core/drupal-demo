<script setup>
import Chart from 'chart.js/auto'
import { onBeforeUnmount, onMounted, useTemplateRef } from 'vue'

const { title, type, labels, series } = defineProps({ title: String, type: String, labels: Array, series: Array })

// The categorical palette, always assigned in this order.
const palette = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#6250d6', '#e34948']
const ink = { secondary: '#52514e', muted: '#898781', grid: '#e1e0d9', baseline: '#c3c2b7' }
const compact = new Intl.NumberFormat(undefined, { notation: 'compact' })
const number = new Intl.NumberFormat()

Chart.defaults.font.family = 'system-ui, -apple-system, "Segoe UI", sans-serif'

const marks = {
  bar: (i) => ({ backgroundColor: palette[i], borderRadius: 4, borderSkipped: 'start', maxBarThickness: 24 }),
  line: (i) => ({
    borderColor: palette[i],
    backgroundColor: `${palette[i]}1a`,
    fill: series.length === 1,
    borderWidth: 2,
    tension: 0.3,
    pointRadius: 0,
    pointHoverRadius: 5,
    pointHoverBorderColor: '#fff',
    pointHoverBorderWidth: 2,
  }),
  doughnut: () => ({ backgroundColor: palette, borderColor: '#fff', borderWidth: 2 }),
}

const canvas = useTemplateRef('canvas')
let chart

onMounted(() => {
  const doughnut = type === 'doughnut'
  chart = new Chart(canvas.value, {
    type,
    data: {
      labels,
      datasets: series.map((serie, i) => ({ label: serie.name, data: serie.values, ...marks[type](i) })),
    },
    options: {
      maintainAspectRatio: false,
      cutout: doughnut ? '62%' : 0,
      interaction: { mode: doughnut ? 'nearest' : 'index', intersect: doughnut },
      plugins: {
        legend: {
          display: doughnut || series.length > 1,
          position: 'bottom',
          labels: { color: ink.secondary, usePointStyle: true, pointStyle: type === 'line' ? 'line' : 'rectRounded' },
        },
        tooltip: {
          boxWidth: 12,
          boxHeight: 2,
          callbacks: { label: (item) => ` ${number.format(item.raw)}  ${doughnut ? item.label : item.dataset.label}` },
        },
      },
      scales: doughnut ? {} : {
        x: { grid: { display: false }, border: { color: ink.baseline }, ticks: { color: ink.muted } },
        y: {
          beginAtZero: true,
          grid: { color: ink.grid },
          border: { display: false },
          ticks: { color: ink.muted, callback: (value) => compact.format(value) },
        },
      },
    },
  })
})

onBeforeUnmount(() => chart?.destroy())
</script>

<template>
  <figure class="nd-card">
    <figcaption class="nd-card__title">{{ title }}</figcaption>
    <div class="nd-chart"><canvas ref="canvas" role="img" :aria-label="title" /></div>
    <details class="nd-data">
      <summary>Data table</summary>
      <table class="nd-table">
        <thead>
          <tr>
            <th />
            <th v-for="serie in series" :key="serie.name" class="is-numeric">{{ serie.name }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(label, i) in labels" :key="label">
            <th>{{ label }}</th>
            <td v-for="serie in series" :key="serie.name" class="is-numeric">{{ number.format(serie.values[i]) }}</td>
          </tr>
        </tbody>
      </table>
    </details>
  </figure>
</template>
