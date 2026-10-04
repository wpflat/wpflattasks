<script setup>
import { computed } from 'vue';
import { PageHeader, sdk } from '../core';
import Projects from './Projects.vue';
import Tasks from './Tasks.vue';
import TasksAnalytics from './TasksAnalytics.vue';

const props = defineProps( { tab: { type: String, default: 'mine' } } );
const section = computed( () => ( [ 'mine', 'all', 'projects', 'analytics' ].includes( props.tab ) ? props.tab : 'mine' ) );
const HEAD = computed( () => ( {
	mine: [ 'My tasks', 'The tasks assigned to you.' ],
	all: [ 'All tasks', 'Everything that needs doing, across the team.' ],
	projects: [ sdk.tp( 'project' ), 'Bigger pieces of work, with their tasks inside.' ],
	analytics: [ 'Analytics', 'How work is flowing, by ' + sdk.t( 'department' ).toLowerCase() + ', person and ' + sdk.t( 'project' ).toLowerCase() + '.' ],
} ) );
</script>

<template>
	<div class="bd-work">
		<PageHeader eyebrow="Tasks" :title="HEAD[ section ][ 0 ]" :description="HEAD[ section ][ 1 ]" />
		<Tasks v-if="section === 'mine'" scope="mine" embedded use-route-filters />
		<Tasks v-else-if="section === 'all'" scope="all" embedded use-route-filters />
		<TasksAnalytics v-else-if="section === 'analytics'" />
		<Projects v-else embedded use-route-filters />
	</div>
</template>
