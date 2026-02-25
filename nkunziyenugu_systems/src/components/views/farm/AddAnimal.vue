<template>
    <div class="add-animal">
        <h2>Add Animal</h2>
        <form @submit.prevent="createAnimal">
            <div>
                <label>Name:</label>
                <input v-model="form.name" required />
            </div>
            <div>
                <label>Species:</label>
                <input v-model="form.species" required />
            </div>
            <button type="submit">Create Animal</button>
        </form>
    </div>
</template>

<script>
export default {
    data() {
        return {
            form: {
                name: '',
                species: ''
            }
        }
    },
    methods: {
        async createAnimal() {
            try {
                const response = await fetch('/api/animals', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.form)
                })
                if (response.ok) {
                    alert('Animal created')
                    this.form = { name: '', species: '' }
                }
            } catch (error) {
                console.error(error)
            }
        }
    }
}
</script>