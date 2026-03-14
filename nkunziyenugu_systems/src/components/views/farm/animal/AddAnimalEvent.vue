<template>
    <div class="form-wrapper">
        <div class="form-container">
            <div class="form-right">
            <h2 class="title">Add Animal Event</h2>
                <form @submit.prevent="submitEvent">
                    <div class="input-group">
                        <select id="eventType" v-model="eventType" required>
                            <option value="" disabled selected>Select Event Type</option>
                            <option value="Health Check">Health Check</option>
                            <option value="Vaccination">Vaccination</option>
                            <option value="Breeding">Breeding</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="input-group">
                        <textarea id="eventDescription" v-model="eventDescription" placeholder="Provide details about the event..." required></textarea>
                    </div>

                    <button type="submit" class="button-info">Submit Event</button>
                    <button type="button" class="button-warning" @click="this.$router.push('/Farm/AnimalList')">Back</button>
                </form>
            </div>
        </div>
    </div>
</template>
<script>
import api from '../../../../store/services/api';
export default {
    data() {
        return {
            eventType: '',
            eventDescription: ''
        }
    },
    methods: {

        submitEvent() {
            const eventData = {
                eventType: this.eventType,
                eventDescription: this.eventDescription
            };

            api.post('/animal-event', eventData)
                .then(response => {
                    console.log('Event added successfully:', response.data);
                    this.$router.push('/Farm/AnimalList');
                })
                .catch(error => {
                    console.error('Error adding event:', error);
                });
        }

    }
}

</script>

<style scoped>
</style>