<template>
  <div v-if="animal">
    <h2 class="text-xl font-bold mb-2">
      {{ animal.global_tag }}
    </h2>

    <p>Status: {{ animal.status }}</p>

    <h3 class="mt-6 font-semibold">History</h3>

    <ul class="mt-2">
      <li v-for="event in animal.events" :key="event.id">
        {{ event.event_date }} - {{ event.event_type }}
      </li>
    </ul>
  </div>
</template>

<script>
export default {
  name: "AnimalDetail",
  props: {
    animal: {
      type: Object,
      required: true
    }
  }
}
</script>
<style scoped></style>