<template>
    <div class="AnimalDeviceLink">
        <div class="app-loading-bar"></div>
        <div class="form-wrapper">
            <div class="form-container">
                <div class="form-right">
                    <h2 class="title">Link Animal to Device</h2>
                    <form>
                        <div class="input-group">
                            <input 
                                @mouseenter="showTooltip = true" 
                                @mouseleave="showTooltip = false"
                                type="text" placeholder="Animal notes for easy identification" required v-model="form.notes" disabled />
                                <!-- Tooltip -->
                                <span class="tooltip-text">Animal description for easy identification.</span>
                        </div>
                        <div class="input-group">
                            <input 
                            @mouseenter="showTooltip = true" 
                            @mouseleave="showTooltip = false"
                            type="number" min="1" max="100000" placeholder="Ear tag number" required v-model="form.animal_tag" disabled />
                            <!-- Tooltip -->
                            <span class="tooltip-text">Ear tag number is a unique identifier for each animal.</span>
                        </div>

                        <div class="input-group">
                            <select id="device" required v-model="form.device_id">
                                <option value="" disabled selected>Select Device to link</option>
                                <option :value="device.id" v-for="device in devices" :key="device.id">
                                    {{ device.name }}
                                </option>
                            </select>
                        </div>

                        <button type="button" class="button-info" @click="linkDevice()">Link</button>
                        <button type="button" class="button-warning" @click="this.$router.push('/Farm/AnimalList')">Back</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import api from "../../../../store/services/api";
import { useToast } from "vue-toastification";
const toast = useToast();
export default {
    name: "AnimalDeviceLink",
    data() {
        return {
            devices: [],
            animals: [],
            animal: {},
            form: {
                account_id: '',
                animal_id: '',
                device_id: '',
                linked_from: '',
                linked_to: '',
                notes: '',
                animal_tag: ''
            }
        }
    },
    mounted() {
        this.getAnimal();``
        //Get device list
        this.$store.dispatch("getDeviceList", {
            account_id: localStorage.getItem("account_id"),
        });
        this.$store.subscribe((mutation) => {
            if (mutation.type === "SET_DEVICE_LIST") {
                this.devices = mutation.payload;
            }
        })
        //End get device list
    },

    methods: {
        getAnimal() {
            this.$store.dispatch('getAnimal', 
            this.$route.params.id
        );
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
        if (this.form.device_id == '') {
            toast.warning('Please select a device.');
            return;
        }
        try {
            const response = await api.post('farm/animals/devices/link', 
            {
                account_id: this.form.account_id,
                animal_id: this.form.animal_id,
                device_id: this.form.device_id
            });

            if(response.status == 201) 
            {
                toast.success(response.data.message);
                this.$router.push('/Farm/AnimalList');
            }
            if(response.status == 202) 
            {
                toast.warning(response.data.message);
            }
        } 
        catch (error) {
            if(error.status == 409) {
                let confirmation = confirm(error.response.data.message);
                if(confirmation) {
                    const response = await api.post('farm/animals/devices/transfer', 
                    {
                        account_id: this.form.account_id,
                        animal_id: this.form.animal_id,
                        device_id: this.form.device_id
                    });
                    if(response.status == 201) 
                    {
                        toast.success(response.data.message);
                        this.$router.push('/Farm/AnimalList');
                    }
                }
                else {
                    return;
                }
            }
        }
    }
    }
}
</script>

<style scoped>

</style>