<template>
  <div>
    <h2 class="text-xl font-bold mb-4">Farm P&L</h2>

    <div class="flex gap-2 mb-4">
      <input type="date" v-model="from" class="input"/>
      <input type="date" v-model="to" class="input"/>
      <button @click="loadReport" class="button-primary">Load</button>
    </div>

    <div v-if="report">
      <p>Total Income: {{ report.income }}</p>
      <p>Total Expenses: {{ report.expense }}</p>
      <p>Total Losses: {{ report.loss }}</p>
      <p class="font-bold">Profit: {{ report.profit }}</p>
    </div>
  </div>
</template>

<script>
export default {
  name: "PnlReport",
  data() {
    return {
      from: "",
      to: "",
      report: null
    }
  },

  mounted() {
    const today = new Date().toISOString().substr(0, 10)
    this.from = today
    this.to = today
    this.loadReport()
  },

  methods: {
    async loadReport() {
      try {
        const res = await this.$store.dispatch("farm/getPnlReport", { from: this.from, to: this.to })
        this.report = res.data
      } catch (err) {
        console.error("failed loading pnl report", err)
      }
    }
  }
}

</script>
<style scoped></style>