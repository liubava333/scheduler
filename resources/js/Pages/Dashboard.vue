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
}

const onTimeRangeSelected = async (args) => {
    const calendar = args.control;
    if (lastClickedBadge === 'icon-plus') {
        alert("Ви натиснули на ПЛЮС!");
        calendar.clearSelection();
        return; // Перериваємо виконання, щоб не спрацювала логіка кліку по клітинці
    }
    calendar.clearSelection();

    const colorDropdownHtml = `
        <div class="custom-dropdown" style="position: relative; ">
            <div class="dropdown-selected" id="selectedItem">
                <span class="color-box" style="background-color: #ccc; width:30px; height:20px; display:inline-block; margin-right:5px;"></span>
                Виберіть колір
            </div>
            <div class="dropdown-options" id="options" style="display:none; position:absolute;max-height: 200px;overflow-y:auto; box-sizing:border-box; background:white; border:1px solid #ccc; z-index:1000;">
                <div class="dropdown-option" data-value="#FF0000" style="padding:5px; cursor:pointer;"><span class="color-box" style="background-color: red; width:30px; height:20px; display:inline-block;"></span> Червоний</div>
                <div class="dropdown-option" data-value="#CD5C5C" style="padding:5px; cursor:pointer;"><span class="color-box" style="background-color: indianRed; width:30px; height:20px; display:inline-block;"></span> Індійський червоний</div>
                <div class="dropdown-option" data-value="#FF7F50" style="padding:5px; cursor:pointer;"><span class="color-box" style="background-color: coral; width:30px; height:20px; display:inline-block;"></span> Кораловий</div>
                <div class="dropdown-option" data-value="#FFA500" style="padding:5px; cursor:pointer;"><span class="color-box" style="background-color: orange; width:30px; height:20px; display:inline-block;"></span> Помаранчевий</div>
                <div class="dropdown-option" data-value="#F0E68C" style="padding:5px; cursor:pointer;"><span class="color-box" style="background-color: khaki; width:30px; height:20px; display:inline-block;"></span> Хакі</div>
                <div class="dropdown-option" data-value="#FFFF00" style="padding:5px; cursor:pointer;"><span class="color-box" style="background-color: yellow; width:30px; height:20px; display:inline-block;"></span> Жовтий</div>
                <div class="dropdown-option" data-value="#9ACD32" style="padding:5px; cursor:pointer;"><span class="color-box" style="background-color: yellowGreen; width:30px; height:20px; display:inline-block;"></span> Жовто-зелений</div>
                <div class="dropdown-option" data-value="#00FF00" style="padding:5px; cursor:pointer;"><span class="color-box" style="background-color: lime; width:30px; height:20px; display:inline-block;"></span> Лайм</div>
                <div class="dropdown-option" data-value="#008000" style="padding:5px; cursor:pointer;"><span class="color-box" style="background-color: green; width:30px; height:20px; display:inline-block;"></span> Зелений</div>
                <div class="dropdown-option" data-value="#808000" style="padding:5px; cursor:pointer;"><span class="color-box" style="background-color: olive; width:30px; height:20px; display:inline-block;"></span> Оливковий</div>
                <div class="dropdown-option" data-value="#3CB371" style="padding:5px; cursor:pointer;"><span class="color-box" style="background-color: seaGreen; width:30px; height:20px; display:inline-block;"></span> Морський зелений</div>
                <div class="dropdown-option" data-value="#008080" style="padding:5px; cursor:pointer;"><span class="color-box" style="background-color: teal; width:30px; height:20px; display:inline-block;"></span> Зеленувато-блакитний</div>
                <div class="dropdown-option" data-value="#0000FF" style="padding:5px; cursor:pointer;"><span class="color-box" style="background-color: blue; width:30px; height:20px; display:inline-block;"></span> Синій</div>
                <div class="dropdown-option" data-value="#7FFFD4" style="padding:5px; cursor:pointer;"><span class="color-box" style="background-color: aquamarine; width:30px; height:20px; display:inline-block;"></span> Аквамарин</div>
                <div class="dropdown-option" data-value="#800080" style="padding:5px; cursor:pointer;"><span class="color-box" style="background-color: purple; width:30px; height:20px; display:inline-block;"></span> Фіолетовий</div>
                <div class="dropdown-option" data-value="#FF00FF" style="padding:5px; cursor:pointer;"><span class="color-box" style="background-color: fuchsia; width:30px; height:20px; display:inline-block;"></span> Фуксія</div>
                <div class="dropdown-option" data-value="#FF1493" style="padding:5px; cursor:pointer;"><span class="color-box" style="background-color: deepPink; width:30px; height:20px; display:inline-block;"></span> Рожевий</div>
                <div class="dropdown-option" data-value="#FF69B4" style="padding:5px; cursor:pointer;"><span class="color-box" style="background-color: hotPink; width:30px; height:20px; display:inline-block;"></span> Гарячий рожевий</div>
                <div class="dropdown-option" data-value="#FFFFFF" style="padding:5px; cursor:pointer;"><span class="color-box" style="background-color: white; width:30px; height:20px; display:inline-block;"></span> Білий</div>
                <div class="dropdown-option" data-value="#808080" style="padding:5px; cursor:pointer;"><span class="color-box" style="background-color: gray; width:30px; height:20px; display:inline-block;"></span> Сірий</div>
            </div>
        </div>`;

    const form = [
        { name: "Name", id: "name", type: "text" },
        { name: "Phone", id: "phone", type: "text" },
        { name: "Date", id: "date", type: "date", disabled: true },
        { name: "Start", id: "start", type: "time", timeInterval: 30 },
        { name: "End", id: "end", type: "time", timeInterval: 30 },
        { name: "Note", id: "note", type: "textarea", height: 50 },
        { name: "Color", id: "colorCustom", html: colorDropdownHtml }
    ];

    const data = {
        date: "2026-04-28",
        start: args.start.toString("HH:mm"),
        end: args.start.addMinutes(30).toString("HH:mm"),
        colorCustom: ""
    };

    DayPilot.Modal.form(form, data).then(function(modal) {
        if (modal.canceled) return;
        const date = new DayPilot.Date(modal.result.date).toString("yyyy-MM-dd");
        const startDateTime = date + "T" + modal.result.start;
        const endDateTime = date + "T" + modal.result.end;

        const params = {
            name: modal.result.name,
            phone: modal.result.phone,
            start: startDateTime,
            end: endDateTime,
            id: DayPilot.guid(),
            note: modal.result.note,
            color: modal.result.colorCustom,
        }
        router.post(route('events.store'), params, {
            preserveState: true,
            onSuccess: (res) => {
                const eventId = res.props.flash.eventId;
                const cells = defineEventCells(startDateTime, endDateTime);
                router.post(route('eventcells.bulkStore'),  {event_id: eventId, cells: cells}, {
                    preserveState: true,
                    onSuccess: () => {
                        console.log('Event cells Added');
                    }
                });
                // Optional: Update DayPilot event with actual server data
                calendar.clearSelection();
            }
        });
    });
    document.addEventListener('click', function(e) {
        const selectedItem = document.getElementById('selectedItem');
        const options = document.getElementById('options');

        if (e.target.closest('#selectedItem')) {
            options.style.display = options.style.display === 'none' ? 'block' : 'none';
        } else if (e.target.closest('.dropdown-option')) {
            const value = e.target.closest('.dropdown-option').getAttribute('data-value');
            selectedItem.innerHTML = e.target.closest('.dropdown-option').innerHTML;
            options.style.display = 'none';
            data.colorCustom = value;
        } else {
            if(options) options.style.display = 'none';
        }
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
    loadEvents();
    getHours();
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
