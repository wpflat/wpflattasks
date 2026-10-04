<script setup>
import { onMounted, reactive, watch } from 'vue';
import { Finished } from '@element-plus/icons-vue';
import { api, StatCard, sdk } from '../core';

const props = defineProps( { clientId: { type: Number, required: true }, tick: { type: Number, default: 0 } } );
const t = reactive( { open: 0, overdue: 0 } );
const load = async () => {
	try {
		const [ a, b ] = await Promise.all( [ api.list( 'tasks', { client_id: props.clientId, open: 1, per_page: 1 } ), api.list( 'tasks', { client_id: props.clientId, due: 'overdue', per_page: 1 } ) ] );
		t.open = a.total;
		t.overdue = b.total;
	} catch ( e ) { /* the card stays at 0 */ }
};
onMounted( load );
watch( () => props.tick, load );
</script>

<template>
	<el-col :xs="12" :md="6"><StatCard :label="'Open ' + sdk.tp( 'task' ).toLowerCase()" :icon="Finished" :tone="t.overdue ? 'danger' : ''" :sub="t.overdue ? t.overdue + ' overdue' : 'Nothing overdue'"><span class="bd-stat__text">{{ t.open }}</span></StatCard></el-col>
</template>
