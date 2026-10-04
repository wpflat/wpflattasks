<script setup>
defineProps( { card: { type: Object, default: () => ( {} ) } } );
</script>

<template>
	<template v-if="card.modules && card.modules.tasks">
		<span><b>{{ card.modules.tasks.open_tasks }}</b> open tasks</span>
		<span><b>{{ card.modules.tasks.active_projects }}</b> projects</span>
	</template>
</template>
