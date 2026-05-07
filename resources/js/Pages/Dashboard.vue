<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import {DayPilot, DayPilotCalendar, DayPilotMonth, DayPilotNavigator} from '@daypilot/daypilot-lite-vue';
import {ref, onMounted, toRaw } from 'vue';
import CalendarEvent from "@/Components/CalendarEvent.vue";
import axios from 'axios';
import EventFormModal from '@/Pages/EventFormModal.vue';

const events = ref([]);
const eventCells = ref([]);
const viewType = ref("Week");
const startDate = ref(DayPilot.Date.today());
const dayRef = ref(null);
const weekRef = ref(null);
const monthRef = ref(null);
const colorNotEnabledCells = "#f1efef";
const colorEnabledCells = "#d1e3ff";
const additionalCells = ref([]);
let lastClickedBadge = null;
const eventModalRef = ref(null);
interface CalendarEvent {
    id: number | string;
    name: string;
    start: string;
    end: string;
    color?: string;
    note: string;
}
// 1. Типизируем пропсы (если данные приходят при загрузке)
const props = defineProps<{
    events: CalendarEvent[]
}>();
const modalData = ref({});
const isModalOpen = ref(false);
const page = usePage();
const hours = page.props.hours; // Proxy об'єкт Inertia render
const form = useForm('post', route('dashboard.store'),{
    weekdayStart: hours?.[0]?.weekday_start || '',
    weekdayEnd: hours?.[0]?.weekday_end || '',
    weekendStart: hours?.[0]?.weekend_start || '',
    weekendEnd: hours?.[0]?.weekend_end || '',
});
const calendarMessage = ref("");

const showMessage = (text) => {
    calendarMessage.value = text;
    setTimeout(() => {
        calendarMessage.value = "";
    }, 3000); // Исчезнет через 3 секунды
};
const onBeforeCellRender = (args) => {
    const cellValue = args.cell.start.value;
    const cellDate = args.cell.start;
    const now = DayPilot.Date.now();
    // 1. Обработка прошлого
    if (cellDate < now) {
        args.cell.properties.backColor = colorNotEnabledCells;
        args.cell.properties.cursor = "not-allowed";
        return; // Для прошлого иконки не нужны
    }

    // 2. Определяем базовый статус (рабочий/нерабочий) по графику
    const dayOfWeek = cellDate.getDayOfWeek(); // 0-Нд, 6-Сб
    const hour = cellDate.toString("HH:mm");
    const isWeekend = (dayOfWeek === 0 || dayOfWeek === 6);

    // Получаем лимиты в зависимости от дня
    const startLimit = isWeekend ? form.weekendStart : form.weekdayStart;
    const endLimit = isWeekend ? form.weekendEnd : form.weekdayEnd;

    // Базовая логика: рабочая ячейка или нет
    let isWorking = (hour >= startLimit && hour < endLimit);

    // 3. Накладываем исключения (Additional Cells) из базы
    if (additionalCells.value) {
        const override = additionalCells.value.find(e => {
            const normalizedStart = e.start.replace(" ", "T");
            return normalizedStart === cellValue;
        });
        if (override) {
            isWorking = !!override.is_enabled;
        }
    }

    // 4. Проверяем занятость (Event Cells)
    const isOccupied = eventCells.value?.some(e => e.start === cellValue);

    // 5. Финальный рендер
    if (isOccupied) {
        args.cell.properties.backColor = colorNotEnabledCells;
        args.cell.properties.html = ''; // Убираем иконки, если там уже есть событие
    } else {
        args.cell.properties.backColor = isWorking ? colorEnabledCells : colorNotEnabledCells;

        const badgeIcon = isWorking ? "x" : "+";
        const badgeType = isWorking ? "icon-remove" : "icon-plus";

        args.cell.properties.html = `
            <div class="cell-badge-container">
                <div class="badge" data-info="${badgeType}">${badgeIcon}</div>
            </div>
        `;
    }
};

const addAdditionalCells = (cell, is_enabled) => {
    router.post(route('additional.store'), { cell, is_enabled }, {
        onSuccess: () =>  {
            getAdditionalCells()
        }
    });
}

const onTimeRangeSelected = async (args) => {
    const calendar = args.control;
    const now = new DayPilot.Date();
    const cellValue = args.start.value;

    if (args.start < now) {
        calendar.clearSelection();
        return;
    }
    const isOccupied = eventCells.value?.some(e => e.start === cellValue);
    if (isOccupied) {
        calendar.clearSelection();
        calendar.message("Эта ячейка уже занята");
        return;
    }

    const dayOfWeek = args.start.getDayOfWeek();
    const hour = args.start.toString("HH:mm");
    const isWeekend = (dayOfWeek === 0 || dayOfWeek === 6);

    const startLimit = isWeekend ? form.weekendStart : form.weekdayStart;
    const endLimit = isWeekend ? form.weekendEnd : form.weekdayEnd;

    // Базовый статус по графику
    let isWorking = (hour >= startLimit && hour < endLimit);

    // Проверка исключений из базы (additionalCells)
    if (additionalCells.value) {
        const override = additionalCells.value.find(e => e.start.replace(" ", "T") === cellValue);
        if (override) {
            isWorking = !!override.is_enabled;
        }
    }

    if (lastClickedBadge === 'icon-plus') {
        addAdditionalCells(cellValue, true);
        calendar.clearSelection();
        return;
    }
    if (lastClickedBadge === 'icon-remove') {
        addAdditionalCells(cellValue, false);
        calendar.clearSelection();
        return;
    }
    if (!isWorking) {
        calendar.clearSelection();
        showMessage("Це неробочий час");
        return;
    }

    // ОТКРЫТИЕ МОДАЛКИ (только для рабочих ячеек)
    calendar.clearSelection();
    modalData.value = {
        date: args.start.value,
        start: args.start.toString("HH:mm"),
        end: args.start.addMinutes(30).toString("HH:mm"),
        colorCustom: ""
    };
    eventModalRef.value.open({ modalData });
};

const defineEventCells = (startCell, endCell) => {
    const cellArray = [];

    let current = new Date(startCell);
    const end = new Date(endCell);
    // Функция для форматирования Date в "YYYY-MM-DDTHH:mm:ss"
    const formatDateTime = (date) => {
        const pad = (n) => n.toString().padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
    };

    const diffInMinutes = (end - current) / (1000 * 60);
    if (diffInMinutes <= 30) {
        return [formatDateTime(current)]; // Возвращаем только start
    }

    // Цикл: пока текущее время меньше конечного
    while (current < end) {
        cellArray.push(formatDateTime(current));

        current.setMinutes(current.getMinutes() + 30);
    }

    return cellArray;
}

const onBeforeEventRender = (args) => {
    args.data.barHidden = true;
    args.data.borderRadius = "5px";
    const now = new DayPilot.Date();
    const eventStart = new DayPilot.Date(args.data.start);

    if (eventStart < now) {
        args.data.backColor = "#eeeeee"; // Серый цвет для прошедших событий
        args.data.fontColor = "#aaaaaa"; // Можно также приглушить цвет текста
        args.data.borderColor = "#cccccc";
    } else {
        args.data.backColor = (args.data.color || "#cccccc") + "cc";
        args.data.borderColor = "darker";
    }
};

const onEventMove = (args) => {
    if (args.newStart < new DayPilot.Date()) {
        args.preventDefault();
        DayPilot.Modal.alert("Cannot move to the past.");
        return;
    }
}

const onEventMoved = (args) => {
    const params = {
        name: args.e.data.name,
        phone: args.e.data.phone,
        start: args.newStart.value,
        end: args.newEnd.value,
        id: args.e.data.id,
        note: args.e.data.note,
        color: args.e.data.colorCustom,
    };

    const syncCells = (eventId, start, end) => {
        const cells = defineEventCells(start, end);

        router.delete(route('eventcells.destroy', { eventId }), {
            onSuccess: () => {
                router.post(route('eventcells.bulkStore'), { event_id: eventId, cells }, {
                    onSuccess: () => {
                        loadEventCells();
                    }
                });
            }
        });
    };

    router.patch(route('events.update', { id: args.e.data.id }), params, {
        preserveState: true,
        onSuccess: () => syncCells(args.e.data.id, args.newStart.value, args.newEnd.value)
    });

}

const formatDate = (date) => {
    const startDate = new DayPilot.Date(date);
    return startDate.toString("yyyy/MM/dd");
}

const onEventEdit = async (event) => {
    modalData.value = {
        id: event.data.id,
        name: event.data.name,
        phone: event.data.phone,
        date: formatDate(event.data.start),
        start: new DayPilot.Date(event.data.start).toString("HH:mm"),
        end: new DayPilot.Date(event.data.end).toString("HH:mm"),
        note: event.data.note,
        colorCustom: event.data.color
    };
    eventModalRef.value.open({
        modalData
    });
};

const onEventDelete = async (event) => {
    const modal = await DayPilot.Modal.confirm("Видалити цю подію?");

    if (modal.result === "OK") {
        //  Удаляем из БД через Inertia
        router.delete(route('events.destroy',{id : event.data.id}), {
            onSuccess: () => {
                loadEvents();
                loadEventCells();
                console.log("Event deleted");
            }
        });
    }
}

const loadEvents = () => {
    const newEvents = page.props.events as CalendarEvent[];
    events.value = [...newEvents];
};
const loadEventCells = async () => {
    try {
        const response = await axios.get(route('eventcells.getAll'));
        eventCells.value = response.data.eventCells;
        weekRef.value.control.update();
    } catch (error) {
        console.error('Ошибка при загрузке событий:', error);
    }
};

const getAdditionalCells = async () => {
    axios.get(route('additional.getAll'))
        .then(response => {
            if (response.data.additionalCells && response.data.additionalCells.length > 0) {
                additionalCells.value = toRaw(response.data.additionalCells.flat());
                if (weekRef.value?.control) {
                    weekRef.value.control.update();
                }
                console.log('Additional cells Успешно загружено!');
            }
        })
        .catch(error => {
            console.error('Ошибка загрузки:', error);
        });
}
const getHours = () => {
    router.get('/dashboard',{}, {
        preserveState: true, // без цього не обновляє дані
        onSuccess: (page) => {
            form.weekdayStart = page.props.hours?.[0]?.weekday_start;
            form.weekdayEnd = page.props.hours?.[0]?.weekday_end
            form.weekendStart = page.props.hours?.[0]?.weekend_start
            form.weekendEnd = page.props.hours?.[0]?.weekend_end
        },
    })
}

const saveHours = () => {
    form.post(route('dashboard.store'), {
        preserveScroll: true,
        onSuccess: () =>  {
            weekRef.value.control.update();
        },
        onError: (errors) => {
             console.log('Save failed', errors)
        }
    });
};
const handleSaveEvent = (formData, eventId) => {
    const cells = defineEventCells(formData.start, formData.end);
    router.post(route('eventcells.bulkStore'),  {event_id: eventId, cells: cells}, {
        preserveState: true,
        onSuccess: () => {
            console.log('Event cells Added');
            loadEvents();
            loadEventCells();
        }
    });
}

const handleEditEvent = (oldFormData, formData, eventId) => {
    const cells = defineEventCells(formData.start, formData.end);
    router.post(route('eventcells.bulkStore'),  {event_id: eventId, cells: cells}, {
        preserveState: true,
        onSuccess: () => {
            loadEvents();
            loadEventCells();
        }
    });
}
const addEventListenerClickOnIcon = () => {
    const calendarElement = document.querySelector(".cell-badge-container");
    if (calendarElement) {
        document.addEventListener("pointerdown", function(e) {
            if (e.target && e.target.classList.contains('badge')) {
                lastClickedBadge = e.target.getAttribute('data-info');
            } else {
                lastClickedBadge = null;
            }
        }, true);
    }
}
onMounted(() => {
    getHours();
    loadEventCells();
    loadEvents();
    addEventListenerClickOnIcon();
    getAdditionalCells();
});
</script>

<template>
    <AuthenticatedLayout>
        <div class="py-4">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class=" shadow-sm sm:rounded-lg dark:bg-gray-800 hours-container">
                    <form @submit.prevent="saveHours">
                        <div class="working-hours-container">
                            <div class="hours-row">
                                <label>Weekdays (Mon-Fri):</label>
                                <input type="time" v-model="form.weekdayStart" />
                                <span>-</span>
                                <input type="time" v-model="form.weekdayEnd" />
                            </div>
                            <div class="hours-row">
                                <label>Weekends (Sat-Sun):</label>
                                <input type="time" v-model="form.weekendStart" />
                                <span>-</span>
                                <input type="time" v-model="form.weekendEnd" />
                            </div>
                            <button class="save-hours" type="submit" :disabled="form.processing">Save Hours</button>
                        </div>
                    </form>
                </div>
                <transition name="fade">
                    <div v-if="calendarMessage" class="custom-calendar-message">
                        {{ calendarMessage }}
                    </div>
                </transition>
                <div
                    class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800"
                >
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <div style="display: flex">
                            <div style="margin-right: 10px">
                                <DayPilotNavigator
                                    :selectMode="viewType"
                                    :showMonths="3"
                                    :skipMonths="3"
                                    @timeRangeSelected="args => startDate = args.day">
                                </DayPilotNavigator>
                            </div>
                            <div style="flex-grow: 1;">
                                <div class="buttons">
                                    <button @click="viewType='Day'" :class="{ selected: viewType === 'Day' }">День</button>
                                    <button @click="viewType='Week'" :class="{ selected: viewType === 'Week' }">Тиждень</button>
                                    <button @click="viewType='Month'" :class="{ selected: viewType === 'Month' }">Місяць</button>
                                </div>

                                <DayPilotCalendar
                                    :viewType="'Day'"
                                    :startDate="startDate"
                                    :visible="viewType === 'Day'"
                                    :events="events"
                                    @beforeEventRender="onBeforeEventRender"
                                    @timeRangeSelected="onTimeRangeSelected"
                                    @eventResized="onEventMoved"
                                    @eventMove="onEventMove"
                                    @eventMoved="onEventMoved"
                                    ref="dayRef"
                                >
                                    <template #event="{event}">
                                        <CalendarEvent
                                            :event="event"
                                            :name="event.data.name"
                                            :note="event.data.note"
                                            @edit="onEventEdit"
                                            @delete="onEventDelete"
                                        />
                                    </template>
                                </DayPilotCalendar>
                                <DayPilotCalendar
                                    :viewType="'Week'"
                                    :startDate="startDate"
                                    :visible="viewType === 'Week'"
                                    :events="events"
                                    :eventBorderRadius="5"
                                    :durationBarVisible="false"
                                    @beforeEventRender="onBeforeEventRender"
                                    @timeRangeSelected="onTimeRangeSelected"
                                    @beforeCellRender="onBeforeCellRender"
                                    @eventResized="onEventMoved"
                                    @eventMove="onEventMove"
                                    @eventMoved="onEventMoved"
                                    ref="weekRef"
                                >
                                    <template #event="{data, event}">
                                        <CalendarEvent
                                            :event="event"
                                            :name="event.data.name"
                                            :note="event.data.note"
                                            @edit="onEventEdit"
                                            @delete="onEventDelete"
                                        />
                                    </template>
                                </DayPilotCalendar>
                                <DayPilotMonth
                                    :startDate="startDate"
                                    :visible="viewType === 'Month'"
                                    :events="events"
                                    @beforeEventRender="onBeforeEventRender"
                                    @timeRangeSelected="onTimeRangeSelected"
                                    @eventResized="onEventMoved"
                                    @eventMove="onEventMove"
                                    @eventMoved="onEventMoved"
                                    ref="monthRef"
                                >
                                    <template #event="{event}">
                                        <CalendarEvent
                                            :event="event"
                                            :name="event.data.name"
                                            :use-header="false"
                                            @edit="onEventEdit"
                                            @delete="onEventDelete"
                                        />
                                    </template>
                                </DayPilotMonth>
                                <EventFormModal
                                    :show="isModalOpen"
                                    :initialData="modalData"
                                    @close="isModalOpen = false"
                                    ref="eventModalRef"
                                    @update="handleEditEvent"
                                    @save="handleSaveEvent"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
<style>
.custom-calendar-message {
    position: fixed;
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
    background: #333;
    color: white;
    padding: 10px 20px;
    border-radius: 4px;
    z-index: 9999;
}
.fade-enter-active, .fade-leave-active { transition: opacity 0.5s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.buttons {
    display: inline-flex;
    background-color: #f1f3f4;
    padding: 4px;
    border-radius: 8px;
    margin-bottom: 10px;
    border: 1px solid #dfe1e5;
}

/* Общие стили кнопок */
.buttons button {
    border: none;
    background: transparent;
    padding: 6px 16px;
    font-size: 14px;
    font-weight: 500;
    color: #5f6368;
    cursor: pointer;
    border-radius: 6px;
    transition: all 0.2s ease;
    outline: none;
}

/* Ховер-эффект для неактивных кнопок */
.buttons button:hover:not(.selected) {
    background-color: #e8eaed;
    color: #202124;
}

/* Стиль активной (выбранной) кнопки */
.buttons button.selected {
    background-color: #ffffff;
    color: #1a73e8;
    box-shadow: 0 1px 3px rgba(60, 64, 67, 0.3);
}

/* Эффект при нажатии */
.buttons button:active {
    transform: scale(0.97);
}

.hours-container {
    padding-bottom: 10px;
}
.working-hours div {
    margin-bottom: 1rem;
}

input {
    margin-left: 0.5rem;
}

.working-hours-container {
    max-width: 685px;
    padding: 20px;
    background-color: #ffffff;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    color: #333;
    display: flex;
    flex-direction:row;
    justify-content: space-between;
    gap: 10px 30px;
}

.working-hours-container div {
    margin-bottom: 15px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.hours-row {
    display: flex;         /* Вмикаємо гнучкий контейнер (усі в рядок) */
    align-items: center;   /* Вирівнюємо всі елементи по центру вертикалі */
    gap: 10px;             /* Додаємо невеликий відступ між елементами */
}

.hours-row > label {
    font-weight: 600;
    font-size: 0.9rem;
    color: #555;
    text-align: center;
}

input[type="time"] {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 1rem;
    color: #444;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
    margin-left:0;
}

input[type="time"]:focus {
    border-color: #42b883; /* Колір Vue */
    box-shadow: 0 0 0 3px rgba(66, 184, 131, 0.2);
}

.save-hours {
    width: 30%;
    padding: 10px;
    background-color: #42b883;
    color: white;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: background-color 0.2s;
    max-height:53px;
    align-self: flex-end;
    margin-bottom: 15px;
}

.save-hours:hover {
    background-color: #33a06f;
}

.save-hours:active {
    transform: translateY(1px);
}

.modal_default_background {
    background-color: #000;
    opacity: 0.5;
}

.modal_default_main {
    border-radius: 8px;
    border: 1px solid #ccc;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    font-family: -apple-system, system-ui, "Segoe UI", Roboto, sans-serif;
}

.modal_default_inner {
    padding: 20px;
}

.modal_default_form_item {
    margin-bottom: 15px;
}

.modal_default_form_item_label {
    font-weight: 600;
    color: #333;
    margin-bottom: 5px;
    display: block;
    margin-left: 0.5rem;
}

.modal_default_form_item input,
.modal_default_form_item textarea,
.modal_default_form_item select {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    box-sizing: border-box; /* Important for padding */
    font-family: inherit;
}

.modal_default_form_item input:focus,
.modal_default_form_item textarea:focus {
    border-color: #3879d9;
    outline: none;
    box-shadow: 0 0 3px rgba(56, 121, 217, 0.3);
}

.modal_default_form_item_time_list {
    margin-left: 9px !important;
}

.modal_default_buttons {
    text-align: right;
    padding-top: 10px;
    border-top: 1px solid #eee;
}

.modal_default_buttons button {
    padding: 8px 15px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    border: 1px solid #ccc;
    margin-left: 10px;
}

.modal_default_ok {
    background-color: #3879d9;
    color: white;
    border: 1px solid #3879d9 !important;
}

.modal_default_ok:hover {
    background-color: #2c5da5;
}

.modal_default_cancel {
    background-color: #f4f4f4;
    color: #333;
}

.modal_default_cancel:hover {
    background-color: #ddd;
}

.modal_default_form_item textarea {
    margin-left: 0.5rem;
}

.modal_default_form_item label {
    display: inline-block;
    margin-right: 10px;
    cursor: pointer;
    position: relative;
}

.dropdown-option:hover {
    background-color: #f0f0f0;
}

.cell-badge-container {
    width: 100%;
    height: 100%;
    position: relative;
}

.cell-badge-container .badge {
    display: block;
    position: absolute;
    top: 0;
    right: 6px;
    color: #ababab;
    padding: 0 5px;
    font-size: 18px;
    font-weight: bold;
    z-index: 10;
    cursor: pointer;
}

.cell-badge-container .badge:hover {
    color: #161414;
}

/* Адаптивність: розміщення інпутів в один рядок на десктопах */
@media (min-width: 350px) {
    .working-hours-container div {
        flex-direction: row;
        align-items: center;
        flex-wrap: wrap;
    }

    label {
        flex: 1 1 100%; /* Заголовок на весь рядок */
    }

    input[type="time"] {
        flex: 1;
    }
}
</style>
