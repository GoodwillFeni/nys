<template>
  <div class="perms-page">
    <div class="perms-card">
      <h2>Edit Permissions</h2>
      <p v-if="userName" class="sub">
        <strong>{{ userName }}</strong> · {{ accountName }}
      </p>

      <!-- Preset picker -->
      <section class="block">
        <h3>Preset <span class="hint">(shortcut — ticks the boxes below; you can tweak afterwards)</span></h3>
        <div class="preset-row">
          <select v-model="selectedPreset">
            <option value="" disabled>Choose a preset…</option>
            <option v-for="(_, name) in presets" :key="name" :value="name">{{ name }}</option>
          </select>
          <button type="button" class="button-info" :disabled="!selectedPreset" @click="applyPreset">
            Apply preset
          </button>
        </div>
      </section>

      <!-- Routes grid -->
      <section class="block">
        <h3>Routes <span class="hint">(screens this user can open)</span></h3>
        <div v-for="group in groupedRoutes" :key="group.name" class="group">
          <div class="group-head">
            <label class="select-all">
              <input type="checkbox" :checked="groupAllSelected(group)" @change="toggleGroup(group, $event.target.checked)" />
              <strong>{{ group.name }}</strong>
            </label>
          </div>
          <div class="checks">
            <label v-for="r in group.routes" :key="r.name" class="check">
              <input type="checkbox" :value="r.name" v-model="routeAccess" />
              <span>{{ r.label }}</span>
            </label>
          </div>
        </div>
      </section>

      <!-- Actions -->
      <section class="block">
        <h3>Actions <span class="hint">(buttons this user can click on those screens)</span></h3>
        <div class="checks">
          <label v-for="a in allActions" :key="a.name" class="check">
            <input type="checkbox" :value="a.name" v-model="actionAccess" />
            <span>{{ a.label }}</span>
          </label>
        </div>
      </section>

      <div class="actions-row">
        <button class="button-info" :disabled="loading" @click="save">
          {{ loading ? 'Saving…' : 'Save' }}
        </button>
        <button class="button-warning" type="button" @click="$router.go(-1)">Back</button>
      </div>
    </div>
  </div>
</template>

<script>
import api from "@/store/services/api";
import registry from "@/permissions-registry.json";
import { useToast } from "vue-toastification";
const toast = useToast();

const ALL_ROUTES  = registry.routes.map(r => r.name);
const ALL_ACTIONS = registry.actions.map(a => a.name);
const PRESETS = {
  Owner:      { routes: ALL_ROUTES, actions: ALL_ACTIONS },
  Admin:      { routes: ALL_ROUTES, actions: ['view','add','edit','approve','complete','assign'] },
  FarmWorker: {
    routes: ['FarmDashboard','FarmList','AnimalList','AnimalEventList','AddAnimal','EditAnimal','AddAnimalEvent','InventoryView'],
    actions: ['view','add','edit'],
  },
  ShopKeeper: {
    routes: ['ShopDashboard','ShopProducts','ShopPOS','AdminOrders','ShopCashFlow','ShopSalesSummary','AddProduct'],
    actions: ['view','add','edit','complete'],
  },
  Customer: {
    routes: ['ShopProducts','ShopCart','ShopMyOrders','CustomerCredit','CustomerCreditRequests'],
    actions: ['view','add'],
  },
  Viewer: {
    routes: ['MainDashboard','FarmDashboard','FarmList','AnimalList','AnimalEventList','InventoryView','ShopDashboard','ShopProducts','DevicesList'],
    actions: ['view'],
  },
};

export default {
  name: 'EditPermissions',

  data() {
    return {
      loading: false,
      userName: '',
      accountName: '',
      routeAccess:  [],
      actionAccess: [],
      selectedPreset: '',
      presets: PRESETS,
    };
  },

  computed: {
    userId()    { return Number(this.$route.params.userId); },
    accountId() { return Number(this.$route.params.accountId); },
    allActions() { return registry.actions; },
    groupedRoutes() {
      const order = ['Dashboard','Users & Admin','Farm','Shop','Customer','Devices','Other'];
      const map = {};
      for (const r of registry.routes) {
        (map[r.group] ||= { name: r.group, routes: [] }).routes.push(r);
      }
      return order.filter(n => map[n]).map(n => map[n]);
    },
  },

  mounted() {
    this.load();
  },

  methods: {
    async load() {
      try {
        const res = await api.get(`/users/${this.userId}`);
        const user = res.data.data;
        this.userName = `${user.name} ${user.surname ?? ''}`.trim();

        const acc = (user.accounts || []).find(a => a.id === this.accountId);
        if (acc) {
          this.accountName  = acc.name;
          this.routeAccess  = Array.isArray(acc.route_access)  ? [...acc.route_access]  : [];
          this.actionAccess = Array.isArray(acc.action_access) ? [...acc.action_access] : [];
        } else {
          toast.error('Account not linked to this user');
        }
      } catch (e) {
        toast.error(e.response?.data?.message ?? 'Failed to load user');
      }
    },

    applyPreset() {
      const p = this.presets[this.selectedPreset];
      if (!p) return;
      this.routeAccess  = [...p.routes];
      this.actionAccess = [...p.actions];
    },

    groupAllSelected(group) {
      return group.routes.every(r => this.routeAccess.includes(r.name));
    },

    toggleGroup(group, checked) {
      const names = group.routes.map(r => r.name);
      if (checked) {
        const set = new Set([...this.routeAccess, ...names]);
        this.routeAccess = [...set];
      } else {
        this.routeAccess = this.routeAccess.filter(n => !names.includes(n));
      }
    },

    async save() {
      this.loading = true;
      try {
        // Send ALL the user's accounts (the API's sync() replaces the pivot set).
        // We only update the currently-edited account; others pass through untouched.
        const res = await api.get(`/users/${this.userId}`);
        const user = res.data.data;
        const accounts = (user.accounts || []).map(a => ({
          id: a.id,
          route_access:  a.id === this.accountId ? this.routeAccess  : (a.route_access  || []),
          action_access: a.id === this.accountId ? this.actionAccess : (a.action_access || []),
        }));

        await api.put(`/users/${this.userId}`, {
          name: user.name,
          surname: user.surname,
          email: user.email,
          phone: user.phone ?? null,
          accounts,
        });
        toast.success('Permissions updated');
        this.$router.push('/UserList');
      } catch (e) {
        toast.error(e.response?.data?.message ?? 'Save failed');
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>

<style scoped>
.perms-page {
  padding: 20px;
  display: flex;
  justify-content: center;
  color: #2c3e50;
}
.perms-card {
  background: #fff;
  border-radius: 12px;
  padding: 28px;
  width: 100%;
  max-width: 900px;
  box-shadow: 0 10px 30px rgba(0,0,0,0.08);
  color: #2c3e50;
}
h2 { margin-top: 0; color: #6a5cff; }
.sub { color: #666; margin-top: -8px; margin-bottom: 20px; }
.block { margin-bottom: 24px; }
.block h3 {
  font-size: 14px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  color: #2c3e50;
  border-bottom: 1px solid #eee;
  padding-bottom: 6px;
  margin-bottom: 12px;
}
.hint { font-weight: 400; text-transform: none; color: #888; font-size: 12px; margin-left: 6px; }
.preset-row { display: flex; gap: 10px; align-items: center; }
.preset-row select {
  padding: 8px 12px;
  border: 1px solid #ddd;
  border-radius: 8px;
  flex: 1;
  max-width: 260px;
  color: #2c3e50;
  background: #fff;
}
.group { margin-bottom: 14px; }
.group-head { margin-bottom: 6px; }
.select-all {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
  color: #2c3e50;
}
.select-all strong { color: #2c3e50; }
.checks {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 6px 12px;
}
.check {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 4px 0;
  font-size: 13px;
  cursor: pointer;
  color: #2c3e50;
}
.check span { color: #2c3e50; }
.check input { accent-color: #6a5cff; }
.actions-row { display: flex; gap: 10px; margin-top: 12px; }
</style>
