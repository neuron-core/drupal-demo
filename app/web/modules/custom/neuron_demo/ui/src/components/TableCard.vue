<script setup>
import { computed, ref } from 'vue'

const { title, columns, rows } = defineProps({ title: String, columns: Array, rows: Array })

const sortBy = ref(null)
const ascending = ref(true)

// "1,284", "12%" and "4.5" sort as numbers; anything else sorts as text.
const toNumber = (cell) => {
  const value = String(cell).replace(/[,%\s]/g, '')
  return value === '' ? NaN : Number(value)
}
const numeric = computed(() => columns.map((_, i) => rows.every((row) => !isNaN(toNumber(row[i])))))

const sorted = computed(() => {
  const i = sortBy.value
  if (i === null) {
    return rows
  }
  const compare = numeric.value[i]
    ? (a, b) => toNumber(a[i]) - toNumber(b[i])
    : (a, b) => String(a[i]).localeCompare(String(b[i]))
  return [...rows].sort((a, b) => (ascending.value ? compare(a, b) : compare(b, a)))
})

function sort(i) {
  // A new column starts with the biggest numbers or with A.
  ascending.value = sortBy.value === i ? !ascending.value : !numeric.value[i]
  sortBy.value = i
}
</script>

<template>
  <section class="nd-card">
    <h3 class="nd-card__title">{{ title }}</h3>
    <div class="nd-table-wrap">
      <table class="nd-table">
        <thead>
          <tr>
            <th v-for="(column, i) in columns" :key="column" :class="{ 'is-numeric': numeric[i] }">
              <button type="button" @click="sort(i)">
                {{ column }}<span class="nd-sort">{{ sortBy === i ? (ascending ? '↑' : '↓') : '↕' }}</span>
              </button>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, r) in sorted" :key="r">
            <td v-for="(cell, i) in row" :key="i" :class="{ 'is-numeric': numeric[i] }">{{ cell }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
