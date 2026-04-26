<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import {DayPilot, DayPilotCalendar, DayPilotMonth, DayPilotNavigator} from '@daypilot/daypilot-lite-vue';
import {ref, onMounted} from 'vue';
import CalendarEvent from "@/Components/CalendarEvent.vue";

const events = ref([]);
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
const page = usePage();
const hours = page.props.hours; // Proxy об'єкт
const form = useForm('post', route('dashboard.store'),{
    weekdayStart: hours?.[0]?.weekday_start || '',
    weekdayEnd: hours?.[0]?.weekday_end || '',
    weekendStart: hours?.[0]?.weekend_start || '',
    weekendEnd: hours?.[0]?.weekend_end || '',
});
const colors = [
    {name: "Green", id: "#93c47d"},
    {name: "Blue", id: "#6fa8dc"},
    {name: "Orange", id: "#f6b26b"},
    {name: "Yellow", id: "#ffd966"},
    {name: "Gray", id: "#cccccc"},
];

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
}

const onTimeRangeSelected = async (args) => {
    const calendar = args.control;
    if (lastClickedBadge === 'icon-plus') {
        alert("Ви натиснули на ПЛЮС!");
        calendar.clearSelection();
        return; // Перериваємо виконання, щоб не спрацювала логіка кліку по клітинці
    }

    const modal = await DayPilot.Modal.prompt("Create a new event:", "Event 1");

    calendar.clearSelection();
    if (modal.canceled) { return; }
    calendar.events.add({
        start: args.start,
        end: args.end,
        id: DayPilot.guid(),
        text: modal.result
    });
};

const onBeforeEventRender = (args) => {
    args.data.barHidden = true;
    args.data.borderRadius = "5px";
    args.data.backColor = (args.data.color || "#cccccc") + "cc";
    args.data.borderColor = "darker";
};

const onEventEdit = async  (event) => {
    const form = [
        {name: "Text", id: "text", type: "text"},
        {name: "Color", id: "color", type: "select", options: colors},
    ];
    const modal = await DayPilot.Modal.form(form, event.data);
    if (modal.canceled) {
        return;
    }
    event.data.text = modal.result.text;
    event.data.color = modal.result.color;
};

const onEventDelete = (event) => {
    const data = event.data;
    events.value = events.value.filter(e => e.id !== data.id);
}

const loadEvents = () => {
    const firstDay = DayPilot.Date.today().firstDayOfWeek().addDays(1);
    events.value = [
        { id: 1, start: firstDay.addHours(9), end: firstDay.addHours(10), text: "Event 1", color: "#93c47d"},
        { id: 2, start: firstDay.addDays(1).addHours(11), end: firstDay.addDays(1).addHours(12), text: "Event 2", color: "#cccccc"},
        { id: 3, start: firstDay.addDays(1).addHours(13), end: firstDay.addDays(1).addHours(15), text: "Event 3", color: "#6fa8dc"},
        { id: 4, start: firstDay.addHours(15), end: firstDay.addHours(16), text: "Event 4", color: "#f6b26b"},
        { id: 5, start: firstDay.addHours(11), end: firstDay.addHours(14), text: "Event 5", color: "#ffd966"},
    ];
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
        onSuccess: () => console.log('Save successful'),
        onError: (errors) => console.log('Save failed', errors),
    });
};

onMounted(() => {
    loadEvents();
    getHours();
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
});
</script>

<template>
    <AuthenticatedLayout>
        <div class="py-4">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="  shadow-sm sm:rounded-lg dark:bg-gray-800 hours-container">
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
                                    ref="weekRef"
                                >
                                    <template #event="{event}">
                                        <CalendarEvent
                                            :event="event"
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
