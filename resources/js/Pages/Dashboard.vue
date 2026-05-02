<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import {DayPilot, DayPilotCalendar, DayPilotMonth, DayPilotNavigator} from '@daypilot/daypilot-lite-vue';
import {ref, onMounted} from 'vue';
import CalendarEvent from "@/Components/CalendarEvent.vue";
import axios from 'axios';
import ModalAddEvent from './ModalAddEvent.vue';
import ModalEditEvent from './ModalEditEvent.vue';

const events = ref([]);
const eventCells = ref([]);
const viewType = ref("Week");
const startDate = ref(DayPilot.Date.today());
const dayRef = ref(null);
const weekRef = ref(null);
const monthRef = ref(null);
const colorNotEnabledCells = "#f1efef";
const pastCells = ref([]);
const nonWorkingCells = ref([]);
const enabledCells = ref([]);
let lastClickedBadge = null;
const eventModalRef = ref(null);
const eventModalEditRef = ref(null);
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
const modalEditData = ref({});
const isModalOpen = ref(false);
const page = usePage();
const hours = page.props.hours; // Proxy об'єкт Inertia render
const form = useForm('post', route('dashboard.store'),{
    weekdayStart: hours?.[0]?.weekday_start || '',
    weekdayEnd: hours?.[0]?.weekday_end || '',
    weekendStart: hours?.[0]?.weekend_start || '',
    weekendEnd: hours?.[0]?.weekend_end || '',
});

const onBeforeCellRender = async (args) => {
    // make pastCells
    if (args.cell.start < DayPilot.Date.now()) {
        args.cell.properties.backColor = colorNotEnabledCells;
        pastCells.value.push(args.cell.start);
    } else {
        args.cell.properties.backColor = "#d1e3ff";
        // make nonWorkingCells
        const dayOfWeek = args.cell.start.getDayOfWeek(); // 0 - Нд, 1 - Пн... 6 - Сб
        const date = new DayPilot.Date(args.cell.start.value);
        const hour = date.toString("HH:mm");

        const isWeekend = (dayOfWeek === 0 || dayOfWeek === 6);
        if (isWeekend) {
            if (hour < form.weekendStart || hour >= form.weekendEnd) { // неробочий час
                args.cell.properties.backColor = colorNotEnabledCells;
                nonWorkingCells.value.push(args.cell.start.value);
            } else {
                enabledCells.value.push(args.cell.start.value);
            }
        } else {
            if (hour < form.weekdayStart || hour >= form.weekdayEnd) { // неробочий час
                args.cell.properties.backColor = colorNotEnabledCells;
                nonWorkingCells.value.push(args.cell.start.value);
            }else {
                enabledCells.value.push(args.cell.start.value)
            }
        }
    }
    nonWorkingCells.value.forEach((e) => {
        if (args.cell.start.value === e) {
            const cellId = "cell-" + args.cell.start.getTime();
            args.cell.properties.html =   `
                <style>
                  #${cellId} .badge {
                    display: block;
                    position: absolute;
                    top: 0px;
                    right: 6px;
                    color: #ababab;
                    padding: 0px 5px;
                    font-size: 18px;
                    font-weight: bold;
                    z-index: 10;
                  }
                  .scheduler_default_cell:hover #${cellId} .badge,
                  .calendar_default_cell:hover #${cellId} .badge {
                    display: block;
                    color: #161414;
                  }
                </style>
                <div id="${cellId}" class="parent_badge"  style="width: 100%; height: 100%; position: relative;">
                  <div class="badge" data-info="icon-plus" >+</div>
                </div>
              `;
            }
        })
    enabledCells.value.forEach((e) => {
        if (args.cell.start.value === e) {
            const cellId = "cell-" + args.cell.start.getTime();
            args.cell.properties.html =   `
                <style>
                  #${cellId} .badge {
                    display: block;
                    position: absolute;
                    top: 0px;
                    right: 6px;
                    color: #ababab;
                    padding: 0px 5px;
                    font-size: 18px;
                    font-weight: bold;
                    z-index: 10;
                  }
                  .scheduler_default_cell:hover #${cellId} .badge,
                  .calendar_default_cell:hover #${cellId} .badge {
                    display: block;
                    color: #161414;
                  }
                </style>
                <div id="${cellId}" class="parent_badge" style="width: 100%; height: 100%; position: relative;">
                <div class="badge" data-info="icon-plus">x</div>
                </div>
              `;
        }
    })

    if (eventCells.value && eventCells.value.length > 0) {
        eventCells.value.forEach((e) => {
            if (args.cell.start.value === e.start) {
                args.cell.properties.html = '';
                args.cell.properties.backColor = colorNotEnabledCells;
            }
        });
    }
}
const onTimeRangeSelected = async (args) => {
    const calendar = args.control;
    if (lastClickedBadge === 'icon-plus') {
        alert("Ви натиснули на ПЛЮС!");
        calendar.clearSelection();
        return; // Перериваємо виконання, щоб не спрацювала логіка кліку по клітинці
    }
    calendar.clearSelection();

    modalData.value = {
        date: args.start.value,
        start: args.start.toString("HH:mm"),
        end: args.start.addMinutes(30).toString("HH:mm"),
        colorCustom: ""
    };
    eventModalRef.value.open({
        modalData
    });
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
    args.data.backColor = (args.data.color || "#cccccc") + "cc";
    args.data.borderColor = "darker";
};

const onEventResized = (args) => {
    // have to DO
}

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
    modalEditData.value = {
        id: event.data.id,
        name: event.data.name,
        phone: event.data.phone,
        date: formatDate(event.data.start),
        start: new DayPilot.Date(event.data.start).toString("HH:mm"),
        end: new DayPilot.Date(event.data.end).toString("HH:mm"),
        note: event.data.note,
        colorCustom: event.data.color
    };
    eventModalEditRef.value.open({
        modalEditData
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
            // weekRef.value.control.update();
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
    const calendarElement = document.querySelector(".parent_badge");
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
                                    <button @click="viewType='Day'" :class="{ selected: viewType === 'Day' }">Day</button>
                                    <button @click="viewType='Week'" :class="{ selected: viewType === 'Week' }">Week</button>
                                    <button @click="viewType='Month'" :class="{ selected: viewType === 'Month' }">Month</button>
                                </div>
                                <DayPilotCalendar
                                    :viewType="'Day'"
                                    :startDate="startDate"
                                    :visible="viewType === 'Day'"
                                    :events="events"
                                    @beforeEventRender="onBeforeEventRender"
                                    @timeRangeSelected="onTimeRangeSelected"
                                    ref="dayRef"
                                >
                                    <template #event="{event}">
                                        <CalendarEvent
                                            :event="event"
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
                                    @eventResized="onEventResized"
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
                                    ref="monthRef"
                                >
                                    <template #event="{event}">
                                        <CalendarEvent
                                            :event="event"
                                            :use-header="false"
                                            @edit="onEventEdit"
                                            @delete="onEventDelete"
                                        />
                                    </template>
                                </DayPilotMonth>
                                <ModalAddEvent
                                    :show="isModalOpen"
                                    :initialData="modalData"
                                    @close="isModalOpen = false"
                                    ref="eventModalRef"
                                    @save="handleSaveEvent"
                                />
                                <ModalEditEvent
                                    :show="isModalOpen"
                                    :initialData="modalEditData"
                                    @close="isModalOpen = false"
                                    ref="eventModalEditRef"
                                    @update="handleEditEvent"
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
.hours-container {
    padding-bottom: 10px;
}
.working-hours div {
    margin-bottom: 1rem;
}
input {
    margin-left: 0.5rem;
}

/* Стилізація робочих клітинок (опціонально) */
:deep(.calendar_default_cell_business) {
    background-color: #ffffff;
}
:deep(.calendar_default_cell) {
    background-color: #b51c1c;
}
/* Контейнер */
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

/* Рядки з інпутами */
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

/* Стилізація полів вводу часу */
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

/* Кнопка */
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
/* 1. Modal Background Mask */
.modal_default_background {
    background-color: #000;
    opacity: 0.5;
}

/* 2. Main Modal Container */
.modal_default_main {
    border-radius: 8px;
    border: 1px solid #ccc;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    font-family: -apple-system, system-ui, "Segoe UI", Roboto, sans-serif;
}

/* 3. Modal Inner Content Padding */
.modal_default_inner {
    padding: 20px;
}

/* 4. Form Items (Labels + Inputs) */
.modal_default_form_item {
    margin-bottom: 15px;
}

/* Labels */
.modal_default_form_item_label {
    font-weight: 600;
    color: #333;
    margin-bottom: 5px;
    display: block;
    margin-left: 0.5rem;
}

/* Inputs, Textareas, Date/Time pickers */
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

/* Focused Input */
.modal_default_form_item input:focus,
.modal_default_form_item textarea:focus {
    border-color: #3879d9;
    outline: none;
    box-shadow: 0 0 3px rgba(56, 121, 217, 0.3);
}

/* 5. Button Container */
.modal_default_buttons {
    text-align: right;
    padding-top: 10px;
    border-top: 1px solid #eee;
}

/* Buttons General */
.modal_default_buttons button {
    padding: 8px 15px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    border: 1px solid #ccc;
    margin-left: 10px;
}

/* OK Button */
.modal_default_ok {
    background-color: #3879d9;
    color: white;
    border: 1px solid #3879d9 !important;
}

.modal_default_ok:hover {
    background-color: #2c5da5;
}

/* Cancel Button */
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

/*   custom*/
.custom-dropdown {
    position: relative;
    width: 100%;
    user-select: none;
    margin-left: 0.5rem;
}

.dropdown-selected {
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    background: white;
}

.dropdown-options {
    display: none; /* Приховано за замовчуванням */
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    border: 1px solid #ccc;
    border-top: none;
    background: white;
    z-index: 1000;
}

.dropdown-option {
    padding: 10px;
    cursor: pointer;
    display: flex;
    align-items: center;
}

.dropdown-option:hover {
    background-color: #f0f0f0;
}

/* Стиль для кольорового квадрата */
.color-box {
    width: 30px;
    height: 20px;
    margin-right: 10px;
    border-radius: 3px;
    border: 1px solid rgba(0,0,0,0.2);
}

/* Клас для показу списку через JS */
.custom-dropdown.open .dropdown-options {
    display: block;
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
