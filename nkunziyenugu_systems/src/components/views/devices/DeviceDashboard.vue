<template>
  <div class="dashboard">
    <div class="page-header">
      <h4><i class="bi bi-speedometer2"></i> Devices Dashboard</h4>
      <div class="header-actions">
        <button class="button-info button-sm" @click="$router.push({ name: 'DevicesList' })">
          <i class="bi bi-list"></i> All Devices
        </button>
        <button class="button-info button-sm" @click="$router.push({ name: 'AddDevice' })">
          <i class="bi bi-plus-circle"></i> Add Device
        </button>
      </div>
    </div>

    <div v-if="loading" class="text-white">Loading dashboard…</div>

    <template v-else>
      <!-- Summary cards: each one is a clickable filter into the All Devices page. -->
      <div class="summary-row">
        <button class="summary-card outdated" @click="openFilter('outdated_firmware')">
          <i class="bi bi-arrow-up-circle icon"></i>
          <div class="text">
            <span class="value">{{ d.totals.outdated_firmware }} <span class="of">/ {{ d.totals.all }}</span></span>
            <span class="label">Devices on outdated firmware</span>
            <span class="sub">Current: {{ d.thresholds.latest_firmware }}</span>
          </div>
        </button>

        <button class="summary-card low-balance" @click="openFilter('low_balance')">
          <i class="bi bi-wallet2 icon"></i>
          <div class="text">
            <span class="value">{{ d.totals.low_balance }} <span class="of">/ {{ d.totals.all }}</span></span>
            <span class="label">Devices with low balance</span>
            <span class="sub">Below R{{ d.thresholds.low_balance_threshold.toFixed(2) }}</span>
          </div>
        </button>

        <button class="summary-card not-reporting" @click="openFilter('not_reporting')">
          <i class="bi bi-wifi-off icon"></i>
          <div class="text">
            <span class="value">{{ d.totals.not_reporting }} <span class="of">/ {{ d.totals.all }}</span></span>
            <span class="label">Devices not reporting</span>
            <span class="sub">Quiet &gt; {{ d.thresholds.stale_report_hours }}h</span>
          </div>
        </button>
      </div>

      <!-- Recent activity — last 5 messages from any device in the account. -->
      <div class="section-card mt-3">
        <div class="section-header">
          <h5><i class="bi bi-clock-history"></i> Recent activity</h5>
          <button class="button-info button-sm" @click="loadDashboard" :disabled="loading">
            <i class="bi bi-arrow-clockwise"></i> Refresh
          </button>
        </div>
        <table v-if="d.recent_activity.length">
          <thead>
            <tr>
              <th>Time</th>
              <th>Device</th>
              <th>Type</th>
              <th>Summary</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(a, i) in d.recent_activity" :key="i">
              <td>{{ relativeTime(a.timestamp) }}</td>
              <td>
                <a href="#" @click.prevent="$router.push({ name: 'DeviceLogs', params: { id: a.device_id } })">
                  {{ a.device_name ?? '—' }}
                </a>
              </td>
              <td><span class="badge" :class="'type-' + a.type">{{ a.type }}</span></td>
              <td>{{ a.summary }}</td>
            </tr>
          </tbody>
        </table>
        <p v-else class="muted">No messages from any device yet.</p>
      </div>
    </template>
  </div>
</template>

<script>
import api from '@/store/services/api';
import { useToast } from 'vue-toastification';
const toast = useToast();

export default {
  name: 'DeviceDashboard',

  data() {
    return {
      loading: true,
      d: {
        totals: { all: 0, outdated_firmware: 0, low_balance: 0, not_reporting: 0 },
        recent_activity: [],
        thresholds: { latest_firmware: '?', low_balance_threshold: 0, stale_report_hours: 24 },
      },
    };
  },

  mounted() {
    this.loadDashboard();
  },

  methods: {
    async loadDashboard() {
      this.loading = true;
      try {
        const res = await api.get('/devices/dashboard');
        // Backend wraps in {status, data:{totals,recent_activity,thresholds}}
        this.d = res.data?.data ?? this.d;
      } catch (e) {
        toast.error(e.response?.data?.message || 'Failed to load device dashboard.');
      } finally {
        this.loading = false;
      }
    },

    openFilter(filter) {
      this.$router.push({ name: 'DevicesList', query: { filter } });
    },

    // "5h ago", "3d ago", "just now". Accepts ISO 8601 strings.
    relativeTime(value) {
      if (!value) return '-';
      const t = new Date(value).getTime();
      if (!Number.isFinite(t)) return '-';
      const diff = Math.max(0, Math.floor((Date.now() - t) / 1000));
      if (diff < 60)    return 'just now';
      if (diff < 3600)  return `${Math.floor(diff / 60)}m ago`;
      if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
      return `${Math.floor(diff / 86400)}d ago`;
    },
  },
};
</script>

<style scoped>
.dashboard {
  padding: 10px;
  color: #e0e0e0;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}
.page-header h4 {
  margin: 0;
  color: #fff;
  font-size: 18px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.header-actions { display: flex; gap: 8px; }

/* Summary cards — clickable filter links into All Devices */
.summary-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 14px;
  margin-bottom: 20px;
}

.summary-card {
  background: rgba(255, 255, 255, 0.08);
  border-radius: 10px;
  padding: 18px 20px;
  display: flex;
  align-items: center;
  gap: 14px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  text-align: left;
  cursor: pointer;
  font: inherit;
  color: inherit;
  transition: background 0.15s, transform 0.05s;
}
.summary-card:hover { background: rgba(255, 255, 255, 0.13); }
.summary-card:active { transform: translateY(1px); }

.summary-card .icon       { font-size: 28px; }
.summary-card .text       { display: flex; flex-direction: column; gap: 2px; }
.summary-card .value      { font-size: 22px; font-weight: bold; color: #fff; }
.summary-card .of         { font-weight: normal; color: rgba(255,255,255,0.5); font-size: 14px; }
.summary-card .label      { font-size: 12px; color: rgba(255,255,255,0.7); text-transform: uppercase; }
.summary-card .sub        { font-size: 11px; color: rgba(255,255,255,0.45); }

/* Color cues — left border highlight so the cards read as different concerns */
.summary-card.outdated     { border-left: 3px solid #ffa726; }
.summary-card.outdated .icon { color: #ffa726; }
.summary-card.low-balance  { border-left: 3px solid #ef5350; }
.summary-card.low-balance .icon { color: #ef5350; }
.summary-card.not-reporting { border-left: 3px solid #9e9e9e; }
.summary-card.not-reporting .icon { color: #9e9e9e; }

/* Recent activity card */
.section-card {
  background: rgba(255, 255, 255, 0.06);
  border-radius: 10px;
  padding: 20px;
  border: 1px solid rgba(255, 255, 255, 0.08);
}
.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 14px;
}
.section-header h5 {
  margin: 0; color: #fff; font-size: 15px;
  display: flex; align-items: center; gap: 8px;
}
.mt-3 { margin-top: 16px; }
.muted { color: rgba(255,255,255,0.5); }

.section-card table { width: 100%; border-collapse: collapse; }
.section-card th, .section-card td {
  padding: 8px 10px;
  border-bottom: 1px solid rgba(255,255,255,0.06);
  font-size: 13px;
}
.section-card th { color: rgba(255,255,255,0.55); font-weight: 600; text-align: left; text-transform: uppercase; font-size: 11px; }
.section-card td a { color: #42a5f5; text-decoration: none; }
.section-card td a:hover { text-decoration: underline; }

.badge {
  padding: 3px 8px; border-radius: 12px; font-size: 11px; color: #fff;
  display: inline-block; text-transform: uppercase;
}
.badge.type-heartbeat { background: #42a5f5; }
.badge.type-location  { background: #66bb6a; }
.badge.type-sensor    { background: #ab47bc; }

@media (max-width: 768px) {
  .page-header { flex-direction: column; align-items: flex-start; gap: 8px; }
  .summary-card { padding: 14px 16px; }
}
</style>
