<script setup>
import { computed } from 'vue';
import Projects from '../views/Projects.vue';
import Tasks from '../views/Tasks.vue';

const props = defineProps( { memberId: { type: Number, required: true }, tick: { type: Number, default: 0 }, refresh: { type: Function, default: () => {} } } );
const owner = computed( () => ( { owner_id: props.memberId } ) );
const work = computed( () => ( { assignee_id: props.memberId } ) );
</script>

<template>
	<div>
		<Projects :fixed="owner" embedded :reload-key="tick" @changed="refresh" />
		<div class="bd-mt"><Tasks :fixed="work" embedded :reload-key="tick" @changed="refresh" /></div>
	</div>
</template>
