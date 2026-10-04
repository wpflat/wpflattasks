<script setup>
import { onMounted, ref, watch } from 'vue';
import { api, fmtDate, sdk } from '../core';

const props = defineProps( { clientId: { type: Number, required: true }, tick: { type: Number, default: 0 }, goTab: { type: Function, default: () => {} } } );
const items = ref( [] );
const load = async () => {
	try {
		items.value = ( await api.list( 'tasks', { client_id: props.clientId, open: 1, per_page: 6 } ) ).items;
	} catch ( e ) { items.value = []; }
};
onMounted( load );
watch( () => props.tick, load );
</script>

<template>
	<el-card shadow="never" class="bd-widget">
		<template #header><div class="bd-widget__head"><span>Open {{ sdk.tp( 'task' ).toLowerCase() }}</span><a href="#" @click.prevent="goTab( 'tasks' )">Open {{ sdk.tp( 'task' ).toLowerCase() }}</a></div></template>
		<ul class="bd-list">
			<li v-for="t in items" :key="t.id">
				<div><div class="bd-strong">{{ t.title }}</div><div class="bd-muted bd-small">{{ [ t.project_name, t.assignee_name || 'Unassigned' ].filter( Boolean ).join( ' · ' ) }}</div></div>
				<span :class="[ 'bd-small', t.is_overdue ? 'bd-late' : 'bd-muted' ]">{{ t.due_date ? fmtDate( t.due_date ) : '' }}</span>
			</li>
			<li v-if="!items.length" class="bd-muted">Nothing open for now.</li>
		</ul>
	</el-card>
</template>
