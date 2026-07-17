<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { router } from '@inertiajs/vue3';
import { DayPilot, DayPilotCalendar, DayPilotMonth, DayPilotNavigator } from '@daypilot/daypilot-lite-vue';
import { ref, onMounted, reactive } from 'vue';
import CalendarEvent from "@/Components/CalendarEvent.vue";
import EventFormModal from '@/Pages/EventFormModal.vue';
import { useCalendarShared } from '@/composables/useCalendarShared';
import { useCalendarApi } from '@/composables/useCalendarApi';

const dayRef = ref(null);
const weekRef = ref(null);
const monthRef = ref(null);
const {events, form, additionalCells, eventCells, fetchEvents, fetchAdditionalCells, fetchEventCells, fetchWorkingHours,
    saveWorkingHours, handleEditEvent, handleSaveEvent, defineEventCells} = useCalendarApi(weekRef, dayRef);
const { setupCellRender, getCellStatus, calendarMessage, showMessage } = useCalendarShared();
const viewType = ref("Week");
const startDate = ref(DayPilot.Date.today());
let lastClickedBadge = null;
const eventModalRef = ref(null);
const modalData = ref({});
const isModalOpen = ref(false);
const config = reactive({
    onBeforeCellRender: (args) => {
        setupCellRender(args, {
            additionalCells,
            eventCells,
            form,
            isAdmin: true // Админ видит иконки + и x
        });
    },
    onBeforeEventRender: (args) => {
        args.data.barHidden = true;
        args.data.borderRadius = "5px";
        const now = new DayPilot.Date();
        const eventStart = new DayPilot.Date(args.data.start);
        if (eventStart < now) {
            args.data.backColor = "#eeeeee";
            args.data.fontColor = "#aaaaaa";
            args.data.borderColor = "#cccccc";
        } else {
            args.data.backColor = (args.data.color || "#cccccc") + "cc";
            args.data.borderColor = "darker";
        }
    },
    onTimeRangeSelectedAdmin: async (args) => {
        const calendar = args.control;
        const { isWorking, cellValue } = getCellStatus(args.start, additionalCells, form);

        // 1. Управление рабочим временем (иконки)
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

        // 2. Валидация для создания события
        if (args.start < new DayPilot.Date()) return calendar.clearSelection();

        if (eventCells.value?.some(e => e.start === cellValue)) {
            calendar.clearSelection();
            return showMessage("Ячейка занята");
        }

        if (!isWorking) {
            calendar.clearSelection();
            return showMessage("Це неробочий час");
        }

        // 3. Открытие модалки
        calendar.clearSelection();
        modalData.value = {
            date: cellValue,
            start: args.start.toString("HH:mm"),
            end: args.start.addMinutes(30).toString("HH:mm"),
            isAdmin: true
        };

        eventModalRef.value.open(modalData.value, {
            eventCells: eventCells.value,
            additionalCells: additionalCells.value,
            workingHours: form
        });
    },
    onEventResize: (args) => {
        // 1. Генерируем массив 30-минутных ячеек для нового размера
        const newCells = defineEventCells(args.newStart, args.newEnd);
        const currentEventId = args.e.data.id;

        // Ищем, пересекаются ли новые ячейки с чужими занятыми ячейками
        const isOccupiedByEvent = eventCells.value?.some((cell: any) => {
            // Проверяем совпадение по времени
            const timeMatch = newCells.includes(cell.start);
            // Важно: игнорируем ячейки, которые принадлежат ЭТОМУ ЖЕ событию
            const isNotOwnCell = Number(cell.event_id) !== Number(currentEventId);

            return timeMatch && isNotOwnCell;
        });
        const isBlockedByAdmin = additionalCells.value?.some((cellSetting: any) => {
            const timeMatch = newCells.includes(cellSetting.start);
            const isDisabled = cellSetting.is_enabled === 0; // 0 означает, что ячейка закрыта для записи
            return timeMatch && isDisabled;
        });

        // Если хоть одна ячейка занята — блокируем ресайз
        if (isOccupiedByEvent || isBlockedByAdmin) {
            args.allowed = false;
            args.preventDefault();

            // Динамический текст ошибки в зависимости от причины блокировки
            const errorMsg = isOccupiedByEvent
                ? "Цей час вже зайнятий іншим записом!"
                : "Цей час заблокований адміністратором!";

            DayPilot.Modal.alert(errorMsg);
            return;
        } else {
            const movedArgs = {
                ...args,
                newStart: {
                    value: typeof args.newStart === 'string' ? args.newStart : args.newStart.value
                },
                newEnd: {
                    value: typeof args.newEnd === 'string' ? args.newEnd : args.newEnd.value
                }
            };

            // Передаем адаптированные аргументы в метод сохранения
            config.onEventMoved(movedArgs);
        }
    },
    onEventMove: (args) => {
        if (args.newStart < new DayPilot.Date()) {
            args.preventDefault();
            DayPilot.Modal.alert("Cannot move to the past.");
            return;
        }
    },
    onEventMoved: (args) => {
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
            const cells = defineEventCells(start, end, start);
            router.delete(route('eventcells.destroy', { eventId }), {
                onSuccess: () => {
                    router.post(route('eventcells.bulkStore'), { event_id: eventId, cells }, {
                        onSuccess: () => {
                            fetchEvents();
                            fetchEventCells(weekRef, dayRef);
                        }
                    });
                }
            });
        };

        router.patch(route('events.update', { id: args.e.data.id }), params, {
            preserveState: true,
            onSuccess: () => syncCells(args.e.data.id, args.newStart.value, args.newEnd.value)
        });
    },
    onEventDelete: async (event) => {
        const modal = await DayPilot.Modal.confirm("Видалити цю подію?");

        if (modal.result === "OK") {
            //  Удаляем из БД через Inertia
            router.delete(route('events.destroy',{id : event.data.id}), {
                onSuccess: () => {
                    fetchEvents();
                    fetchEventCells();
                    console.log("Event deleted");
                }
            });
        }
    },
    onEventEdit: async (event) => {
        modalData.value = {
            id: event.data.id,
            name: event.data.name,
            phone: event.data.phone,
            date: event.data.start,
            start: event.data.start.toString("HH:mm"),
            end: event.data.end.toString("HH:mm"),
            note: event.data.note,
            colorCustom: event.data.color,
            isAdmin: true
        };

        eventModalRef.value.open({modalData}, {
            eventCells: eventCells.value,
            additionalCells: additionalCells.value,
            workingHours: form
        });
    }
});

const addAdditionalCells = (cell, is_enabled) => {
    router.post(route('additional.store'), { cell, is_enabled }, {
        onSuccess: () =>  {
            fetchAdditionalCells(weekRef, dayRef, true)
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
onMounted(async() => {
    try {
        await Promise.all([
            // Загружаем всё одной пачкой
            fetchWorkingHours(weekRef, dayRef),
            fetchEvents(),
            addEventListenerClickOnIcon(),
            fetchAdditionalCells(weekRef, dayRef, true), // true = режим админа
            fetchEventCells(weekRef, dayRef),
        ]);

        // Когда все данные в REF-ах, принудительно обновляем календарь
        [weekRef, dayRef].forEach(ref => ref?.value?.control?.update());
    } catch (e) {
        console.error("Ошибка при инициализации данных календаря", e);
    }
});
</script>

<template>
    <AuthenticatedLayout>
        <div class="py-4">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class=" shadow-sm sm:rounded-lg dark:bg-gray-800 hours-container">
                    <div class="settings-panel">
                        <!-- Группа Будни -->
                        <div :class="['input-group', { 'shake-animation': form.errors.weekdayEnd }]">
                            <label>Будние дни:</label>
                            <div class="time-range">
                                <!-- Очищаем ошибку при любом вводе (input) -->
                                <input type="time"
                                       v-model="form.weekdayStart"
                                       @input="form.clearErrors('weekdayEnd')"
                                       :class="{ 'input-error': form.errors.weekdayEnd }">
                                <span>—</span>
                                <input type="time"
                                       v-model="form.weekdayEnd"
                                       @input="form.clearErrors('weekdayEnd')"
                                       :class="{ 'input-error': form.errors.weekdayEnd }">
                            </div>
                            <p v-if="form.errors.weekdayEnd" class="error-text">{{ form.errors.weekdayEnd }}</p>
                        </div>

                        <!-- Группа Выходные -->
                        <div :class="['input-group', { 'shake-animation': form.errors.weekendEnd }]">
                            <label>Выходные:</label>
                            <div class="time-range">
                                <input type="time"
                                       v-model="form.weekendStart"
                                       @input="form.clearErrors('weekendEnd')"
                                       :class="{ 'input-error': form.errors.weekendEnd }">
                                <span>—</span>
                                <input type="time"
                                       v-model="form.weekendEnd"
                                       @input="form.clearErrors('weekendEnd')"
                                       :class="{ 'input-error': form.errors.weekendEnd }">
                            </div>
                            <p v-if="form.errors.weekendEnd" class="error-text">{{ form.errors.weekendEnd }}</p>
                        </div>

                        <button class="save-hours" @click="saveWorkingHours(weekRef)" :disabled="form.processing">
                            Сохранить график
                        </button>
                    </div>

                </div>
                <!-- Уведомление -->
                <Transition name="fade">
                    <div v-if="calendarMessage" class="calendar-toast">
                        {{ calendarMessage }}
                    </div>
                </Transition>
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
                                    @beforeEventRender="config.onBeforeEventRender"
                                    @timeRangeSelected="config.onTimeRangeSelectedAdmin"
                                    @beforeCellRender="config.onBeforeCellRender"
                                    @eventResized="config.onEventResize"
                                    @eventResize="config.onEventResize"
                                    @eventMove="config.onEventMove"
                                    @eventMoved="config.onEventMoved"
                                    ref="dayRef"
                                >
                                    <template #event="{event}">
                                        <CalendarEvent
                                            :event="event"
                                            :name="event.data.name"
                                            :note="event.data.note"
                                            @edit="config.onEventEdit"
                                            @delete="config.onEventDelete"
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
                                    @beforeEventRender="config.onBeforeEventRender"
                                    @timeRangeSelected="config.onTimeRangeSelectedAdmin"
                                    @beforeCellRender="config.onBeforeCellRender"
                                    @eventResized="config.onEventResize"
                                    @eventResize="config.onEventResize"
                                    @eventMove="config.onEventMove"
                                    @eventMoved="config.onEventMoved"
                                    ref="weekRef"
                                >
                                    <template #event="{data, event}">
                                        <CalendarEvent
                                            :event="event"
                                            :name="event.data.name"
                                            :note="event.data.note"
                                            @edit="config.onEventEdit"
                                            @delete="config.onEventDelete"
                                        />
                                    </template>
                                </DayPilotCalendar>
                                <DayPilotMonth
                                    :startDate="startDate"
                                    :visible="viewType === 'Month'"
                                    :events="events"
                                    @beforeEventRender="config.onBeforeEventRender"
                                    @timeRangeSelected="config.onTimeRangeSelectedAdmin"
                                    @eventResized="config.onEventResize"
                                    @eventResize="config.onEventResize"
                                    @eventMove="config.onEventMove"
                                    @eventMoved="config.onEventMoved"
                                    ref="monthRef"
                                >
                                    <template #event="{event}">
                                        <CalendarEvent
                                            :event="event"
                                            :name="event.data.name"
                                            :use-header="false"
                                            @edit="config.onEventEdit"
                                            @delete="config.onEventDelete"
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

</style>
