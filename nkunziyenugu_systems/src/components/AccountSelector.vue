<template>
  <div class="account-selector" v-if="accounts && accounts.length > 1">
    <label class="account-label">Active Account:</label>
    <select 
      :value="selectedAccountId" 
      @change="handleAccountChange"
      class="account-select"
    >
      <option 
        v-for="account in accounts" 
        :key="account.id" 
        :value="account.id"
      >
        {{ account.name }} ({{ account.type }})
      </option>
    </select>
  </div>
  <div class="account-display" v-else-if="activeAccount">
    <span class="account-label">Account:</span>
    <span class="account-name">{{ activeAccount.name }}</span>
  </div>
</template>

<script>
import { useToast } from "vue-toastification";
const toast = useToast();

export default {
  name: "AccountSelector",
  
  computed: {
    accounts() {
      return this.$store.state.auth.accounts || [];
    },
    
    activeAccount() {
      return this.$store.state.auth.activeAccount;
    },
    
    selectedAccountId() {
      return this.activeAccount?.id || null;
    }
  },
  
  methods: {
    handleAccountChange(event) {
      const accountId = parseInt(event.target.value);
      const account = this.accounts.find(acc => acc.id === accountId);
      
      if (account) {
        // Dispatch action to switch account
        this.$store.dispatch('auth/switchAccount', account);
        
        toast.success(`Switched to ${account.name}`);
        
        // Refresh current page data
        this.refreshCurrentPage();
      }
    },
    
    refreshCurrentPage() {
      // Components will automatically refresh via Vuex watchers
      // No need to manually emit events or reload pages
    }
  }
};
</script>

<style scoped>
.account-selector {
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.2);
}

.account-label {
  display: block;
  color: #fff;
  font-size: 12px;
  margin-bottom: 5px;
  font-weight: 500;
}

.account-select {
  width: 100%;
  padding: 8px 12px;
  border-radius: 5px;
  border: 1px solid rgba(255, 255, 255, 0.3);
  background: rgba(255, 255, 255, 0.1);
  color: #fff;
  font-size: 14px;
  cursor: pointer;
  outline: none;
  transition: all 0.3s ease;
}

.account-select:hover {
  background: rgba(255, 255, 255, 0.15);
  border-color: rgba(255, 255, 255, 0.5);
}

.account-select:focus {
  background: rgba(255, 255, 255, 0.2);
  border-color: #6a5cff;
}

.account-select option {
  background: #27253f;
  color: #fff;
  padding: 10px;
}

.account-display {
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.2);
}

.account-name {
  color: #fff;
  font-weight: 500;
  font-size: 14px;
}
</style>
