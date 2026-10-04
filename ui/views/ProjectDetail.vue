<script setup>
import { computed, onMounted, ref } from 'vue';
import { ArrowLeft } from '@element-plus/icons-vue';
import { api, errorMessage, navigate, ElMessage, RecordDrawer, SlotOutlet, LinkedRecords, fmtDate, sdk } from '../core';
import ProjectPhases from '../components/ProjectPhases.vue';
import { PROJECT_STATUS } from '../util';
import { projectFields } from '../resources';

const props = defineProps( { id: { type: [ String, Number ], required: true } } );
const project = ref( null );
const error = ref( '' );
const key = ref( 0 );
const editing = ref( false );
// What other modules see when they add to this page (BizyMoney: price, budget and profit).
const ctx = computed( () => ( { project: project.value, projectId: Number( props.id ), tick: key.value, refresh: () => { key.value++; load(); } } ) );
const hasStats = computed( () => sdk.slot( 'project.stats', ctx.value ).length > 0 );

const load = async () => {
	try {
		project.value = await api.get( 'projects', props.id );
		error.value = '';
	} catch ( e ) {
		error.value = errorMessage( e );
	}
};
onMounted( load );

const changed = () => {
	key.value++;
	load();
};
const save = ( values ) => {
	const body = {};
	projectFields().forEach( ( f ) => ( body[ f.name ] = values[ f.name ] ) );
	return api.update( 'projects', props.id, body );
};
const saved = () => {
	ElMessage.success( 'Saved' );
	load();
};
const setStatus = async ( status ) => {
	try {
		await api.update( 'projects', props.id, { status } );
		load();
	} catch ( e ) {
		ElMessage.error( errorMessage( e ) );
	}
};
</script>

<template>
	<div class="bd-project">
		<el-alert v-if="error" :title="error" type="error" show-icon :closable="false" class="bd-mb" />
		<template v-if="project">
			<div class="bd-pagehead">
				<div>
					<el-button text :icon="ArrowLeft" @click="navigate( '/tasks/projects' )">All {{ $tp( 'project' ).toLowerCase() }}</el-button>
					<span class="bd-eyebrow">{{ $t( 'project' ) }}</span>
					<h1>{{ project.name }}</h1>
					<p class="bd-muted">
						<a v-if="project.client_id" class="bd-link" :href="'#/clients/' + project.client_id">{{ project.client_name }}</a><span v-else>Internal</span>
						· <span :class="[ 'bd-badge', 'bd-badge--' + PROJECT_STATUS[ project.status ].tone ]">{{ PROJECT_STATUS[ project.status ].label }}</span>
						<span v-if="project.due_date"> · Due {{ fmtDate( project.due_date ) }}</span>
						<span v-if="project.owner_name"> · Owner {{ project.owner_name }}</span>
					</p>
				</div>
				<div class="bd-pagehead__actions">
					<SlotOutlet name="project.actions" :ctx="ctx" />
					<el-button v-if="project.status !== 'done'" @click="setStatus( 'done' )">Mark done</el-button>
					<el-button v-else @click="setStatus( 'active' )">Reopen</el-button>
					<el-button type="primary" @click="editing = true">Edit</el-button>
				</div>
			</div>

			<el-card shadow="never" class="bd-mb">
				<div class="bd-prog bd-prog--lg">
					<div class="bd-prog__bar"><span :style="{ width: project.progress + '%' }" /></div>
					<div class="bd-muted">{{ project.task_total ? `${ project.task_done } of ${ project.task_total } tasks done (${ project.progress }%)` : 'No tasks yet — add one inside a phase below.' }}</div>
				</div>
				<p v-if="project.description" class="bd-lead">{{ project.description }}</p>
			</el-card>

			<el-row v-if="hasStats" :gutter="16" class="bd-mb"><SlotOutlet name="project.stats" :ctx="ctx" /></el-row>
			<ProjectPhases :project="project" :reload-key="key" @changed="changed" />
			<SlotOutlet name="project.sections" :ctx="ctx" />
			<LinkedRecords type="project" :id="project.id" title="Linked records" />
			<RecordDrawer v-model="editing" :title="'Edit ' + $t( 'project' ).toLowerCase()" :fields="projectFields()" :record="project" :save="save" @saved="saved" />
		</template>
	</div>
</template>
