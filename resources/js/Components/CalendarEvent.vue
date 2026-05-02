<script setup lang="ts">
const {event, useHeader, onEdit, onDelete} = defineProps({
    event: {type: Object, required: true},
    name: {type: String,},
    note: {type: String,},
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
        {{ name }}
        <span>
      <svg @click="handleDelete">
        <use href="/icons/daypilot.svg#x-2"></use>
      </svg>
      <svg @click="handleEdit">
        <use href="/icons/daypilot.svg#edit"></use>
      </svg>
    </span>
    </div>
    <div>
        <span>{{ note }}</span>
    </div>
</template>

<style scoped>
.event-header svg:hover {
    color: #333333;
}

.event-header {
    display: flex;
    align-items: center;
    justify-content: space-between; /* Текст слева, иконки справа */
    padding: 2px 6px;
    font-size: 12px;
    color: #333333;
    background-color: rgba(0, 0, 0, 0.1); /* Легкая подложка */
    border-radius: 4px 4px 0 0;
}

/* Обертка для текста и иконок */
.event-header span {
    display: flex;
    color: #333333;
    align-items: center;
    width: 100%;
    /* Убираем общий gap здесь, если хотим разное расстояние */
    gap: 0;
}

.event-header svg {
    width: 16px;
    height: 16px;
    cursor: pointer;
    color: #33333366;
    fill: currentColor;
    transition: opacity 0.2s, transform 0.1s;
    /* Это прижмет ПЕРВУЮ иконку вправо, отодвинув её от текста */
    margin-left: auto;
}

.event-header svg:hover {
    opacity: 0.7;
    transform: scale(1.1);
}

/* Специфический класс, если useHeader === true */
.use-header {
    background-color: #ffffff66; /* Цвет выделенного заголовка */
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
}

/* Если иконок несколько, добавим отступ между ними */
.event-header svg + svg {
    margin-left: 1px;
}
</style>
