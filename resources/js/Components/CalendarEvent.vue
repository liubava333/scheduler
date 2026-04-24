<script setup lang="ts">
const {event, useHeader, onEdit, onDelete} = defineProps({
    event: {type: Object, required: true},
    useHeader: {type: Boolean, required: false, default: true},
    onEdit: {type: Function, required: false},
    onDelete: {type: Function, required: false},
});

const handleEdit = () => {
    if (onEdit) {
        onEdit(event);
    }
};

const handleDelete = () => {
    if (onDelete) {
        onDelete(event);
    }
};
</script>

<template>
    <div :class="['event-header', { 'use-header': useHeader }]">
        {{ event.text() }}
        <span>
      <svg @click="handleDelete">
        <use href="/icons/daypilot.svg#x-2"></use>
      </svg>
      <svg @click="handleEdit">
        <use href="/icons/daypilot.svg#edit"></use>
      </svg>
    </span>
    </div>
</template>

<style scoped>
.event-header {
    padding: 4px;
    color: #333333;
    border-radius: 5px;

}

.use-header.event-header {
    background-color: #ffffff66;
    margin-top: 2px;
    margin-left: 2px;
    margin-right: 2px;
}

.event-header span {
    float: right;
}

.event-header svg {
    width: 16px;
    height: 16px;
    margin-left: 4px;
    cursor: pointer;
    color: #33333366;
}

.event-header svg:hover {
    color: #333333;
}
</style>
