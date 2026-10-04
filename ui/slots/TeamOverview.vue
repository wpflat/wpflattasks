<script setup>
import { onMounted, ref, watch } from 'vue';
import { api, fmtDate, sdk } from '../core';

const props = defineProps( { memberId: { type: Number, required: true }, tick: { type: Number, default: 0 }, goTab: { type: Function, default: () => {} } } );
const tasks = ref( [] );
const projects = ref( [] );
const load = async () => {
	try {
		const [ tk, pr ] = await Promise.all( [ api.list( 'tasks', { assignee_id: props.memberId, open: 1, per_page: 8 } ), api.list( 'projects', { owner_id: props.memberId, per_page: 8 } ) ] );
		tasks.value = tk.items;
		projects.value = pr.items;
	} catch ( e ) { /* empty cards */ }
};
onMounted( load );
watch( () => props.tick, load );
</script>

<template>
	<el-col :xs="24" :lg="12">
		<el-card shadow="never" class="bd-widget bd-mb">
			<template #header><div class="bd-widget__head"><span>Doing now</span><a href="#" @click.prevent="goTab( 'tasks' )">All work</a></div></template>
			<ul class="bd-list">
				<li v-for="t in tasks" :key="t.id">
					<div><div class="bd-strong">{{ t.title }}</div><div class="bd-muted bd-small">{{ [ t.project_name, t.client_name ].filter( Boolean ).join( ' · ' ) || 'Internal' }}</div></div>
					<span :class="[ 'bd-small', t.is_overdue ? 'bd-late' : 'bd-muted' ]">{{ t.due_date ? fmtDate( t.due_date ) : '' }}</span>
				</li>
				<li v-if="!tasks.length" class="bd-muted">No open tasks.</li>
			</ul>
		</el-card>
	</el-col>
	<el-col :xs="24" :lg="12">
		<el-card shadow="never" class="bd-widget bd-mb">
			<template #header><div class="bd-widget__head"><span>{{ sdk.tp( 'project' ) }} they own</span></div></template>
			<ul class="bd-list">
				<li v-for="p in projects" :key="p.id">
					<div><a class="bd-strong bd-link" :href="'#/projects/' + p.id">{{ p.name }}</a><div class="bd-muted bd-small">{{ p.client_name || 'Internal' }} · {{ p.task_done }} of {{ p.task_total }} tasks done</div></div>
					<span class="bd-small bd-muted">{{ p.due_date ? fmtDate( p.due_date ) : '' }}</span>
				</li>
				<li v-if="!projects.length" class="bd-muted">Not the owner of any project.</li>
			</ul>
		</el-card>
	</el-col>
</template>
