<template>
  <div class="login-wrapper">
    <div class="login-container">
      <div class="login-right">
        <h2>Link Animal to Device</h2>

        <form @submit.prevent="linkDevice">
          <div class="input-group">
            <input type="text" placeholder="Animal notes" v-model="form.notes" disabled />
          </div>

          <div class="input-group">
            <input type="number" placeholder="Ear tag number" v-model="form.animal_tag" disabled />
          </div>

          <div class="input-group">
            <select v-model="form.device_id" required>
              <option value="" disabled>Select Device to link</option>
              <option v-for="device in devices" :key="device.id" :value="device.id">
                {{ device.name }}
              </option>
            </select>
          </div>

          <div class="row">
            <div class="col-2">
              <button type="submit" class="button-info">Link</button>
            </div>
            <div class="col-2">
              <button type="button" @click="$router.back()" class="button-warning">Back</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import api from '@/store/services/api';
import { useToast } from "vue-toastification";
const toast = useToast();

export default {
  name: "AnimalDeviceLink",

  data() {
    return {
      devices: [],
      animal: {},
      form: {
        account_id: '',
        animal_id: '',
        device_id: '',
        notes: '',
        animal_tag: ''
      }
    }
  },

  mounted() {
    this.getAnimal();
    this.$store.dispatch("getDeviceList", {
      account_id: localStorage.getItem("account_id"),
    });
    this.$store.subscribe((mutation) => {
      if (mutation.type === "SET_DEVICE_LIST") {
        this.devices = mutation.payload;
      }
    });
  },

  methods: {
    getAnimal() {
      this.$store.dispatch('getAnimal', this.$route.params.id);
      this.$store.subscribe((mutation) => {
        if (mutation.type === 'SET_ANIMAL') {
          this.animal = mutation.payload;
          this.form.account_id = this.animal.account_id;
          this.form.animal_id = this.animal.id;
          this.form.notes = this.animal.notes;
          this.form.animal_tag = this.animal.animal_tag;
        }
      });
    },

    async linkDevice() {
      if (!this.form.device_id) {
        toast.warning('Please select a device.');
        return;
      }
      try {
        const response = await api.post('farm/animals/devices/link', {
          account_id: this.form.account_id,
          animal_id: this.form.animal_id,
          device_id: this.form.device_id
        });

        if (response.status == 201) {
          toast.success(response.data.message);
          this.$router.back();
        }
        if (response.status == 202) {
          toast.warning(response.data.message);
        }
      } catch (error) {
        if (error.status == 409) {
          let confirmation = confirm(error.response.data.message);
          if (confirmation) {
            const response = await api.post('farm/animals/devices/transfer', {
              account_id: this.form.account_id,
              animal_id: this.form.animal_id,
              device_id: this.form.device_id
            });
            if (response.status == 201) {
              toast.success(response.data.message);
              this.$router.back();
            }
          }
        }
      }
    }
  }
}
</script>

<style scoped>
.login-wrapper {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  background: linear-gradient(135deg, #27253f, #605a6d);
}

.login-container {
  width: 900px;
  max-width: 95%;
  height: 650px;
  background: #ffffff;
  display: flex;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
}

.login-right {
  flex: 1;
  padding: 50px;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.login-right h2 {
  text-align: center;
  margin-bottom: 25px;
  color: #6a5cff;
}

.input-group {
  margin-bottom: 15px;
}

.input-group input,
.input-group select {
  width: 100%;
  padding: 12px 15px;
  border-radius: 25px;
  border: 1px solid #ddd;
  outline: none;
  font-size: 14px;
  color: #444;
}

.input-group input:focus,
.input-group select:focus {
  border-color: #6a5cff;
}

@media (max-width: 768px) {
  .login-container {
    flex-direction: column;
    height: auto;
  }
}
</style>
