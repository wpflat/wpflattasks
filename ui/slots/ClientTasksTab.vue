<script setup>
import { computed } from 'vue';
import { sdk } from '../core';
import Projects from '../views/Projects.vue';
import Tasks from '../views/Tasks.vue';

const props = defineProps( { clientId: { type: Number, required: true }, tick: { type: Number, default: 0 }, refresh: { type: Function, default: () => {} } } );
const fixed = computed( () => ( { client_id: props.clientId } ) );
</script>

<template>
	<div>
		<h3 class="bd-subhead" style="margin-top: 4px">{{ sdk.tp( 'project' ) }}</h3>
		<Projects :fixed="fixed" embedded :reload-key="tick" @changed="refresh" />
		<h3 class="bd-subhead">{{ sdk.tp( 'task' ) }}</h3>
		<Tasks :fixed="fixed" embedded :reload-key="tick" @changed="refresh" />
	</div>
</template>
