<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { DayPilot, DayPilotCalendar, DayPilotNavigator } from '@daypilot/daypilot-lite-vue';
import EventFormModal from '@/Pages/EventFormModal.vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';
import { ref, reactive, onMounted, onUnmounted } from 'vue';
import { useCalendarShared } from '@/composables/useCalendarShared';
const { setupCellRender, getCellStatus, calendarMessage, showMessage } = useCalendarShared();
const viewType = ref("Week");
const startDate = ref(DayPilot.Date.today());
const events = ref([]);
import { useCalendarApi } from '@/composables/useCalendarApi';
const page = usePage();
const weekRef = ref(null);
const dayRef = ref(null);
const eventModalRef = ref(null);
const modalData = ref({});
const isModalOpen = ref(false);
const {form, additionalCells, eventCells, fetchAdditionalCells,handleSaveEvent,handleEditEvent, fetchEventCells, fetchWorkingHours } = useCalendarApi(weekRef, dayRef);
defineProps<{
    canLogin?: boolean;
    canRegister?: boolean;
    laravelVersion: string;
    phpVersion: string;
}>();
const isBtnVisible = ref(true);
let lastScrollTop = 0;
const config = reactive({
    onBeforeCellRender: (args) => {
        // ЗАЩИТА: Если данные еще не загружены, не пытаемся рендерить логику
        if (!form.weekdayStart || !additionalCells.value) {
            args.cell.properties.backColor = "#62d60f";
            return;
        }

        if (!page.props.auth.user) {
            args.cell.properties.cursor = "pointer";
            // Можно даже добавить текст "Войдите, чтобы записаться" при наведении (через title)
            args.cell.properties.title = "Авторизуйтесь для записи";
        }
        setupCellRender(args, {
            additionalCells,
            eventCells,
            form,
            isAdmin: false // Клиенты просто видят доступные часы без кнопок
        });
    },
    onTimeRangeSelectedPortal: async (args) => {
        const calendar = args.control;
        const page = usePage();
        const { isWorking, cellValue } = getCellStatus(args.start, additionalCells, form);

        // 1. Проверка авторизации
        if (!page.props.auth.user) {
            calendar.clearSelection();
            // Сохраняем намерение записаться (опционально) или просто редиректим
            if (confirm("Щоб записатися, потрібно увійти в систему. Перейти до входу?")) {
                router.get(route('login'));
            }
            return;
        }

        // 2. Базовые проверки (прошлое, занятость, график)
        if (args.start < new DayPilot.Date()) {
            calendar.clearSelection();
            return;
        }

        if (eventCells.value?.some(e => e.start === cellValue)) {
            calendar.clearSelection();
            return showMessage("Цей час уже заброньовано");
        }

        if (!isWorking) {
            calendar.clearSelection();
            return showMessage("Вибачте, цей час недоступний для запису");
        }

        // 3. Открытие окна записи для авторизованного клиента
        calendar.clearSelection();
        modalData.value = {
            date: cellValue,
            start: args.start.toString("HH:mm"),
            end: args.start.addMinutes(30).toString("HH:mm"),
            isAdmin: false
        };
        // eventModalRef.value.open({ modalData });
        eventModalRef.value.open(modalData.value, {
            eventCells: eventCells.value,
            additionalCells: additionalCells.value,
            workingHours: form
        });
    },
});

const getAdditionalCellsPortal = async () => {
    axios.get(route('additional.getAll'))
        .then(response => {
            const data = response.data.additionalCells;
            if (data) {
                // На портале просто заменяем массив свежими данными
                // Можно сразу отфильтровать только рабочие, если нерабочие нам не нужны
                additionalCells.value = data.flat().map(cell => ({
                    ...cell,
                    start: cell.start.replace(" ", "T") // нормализуем формат для DayPilot
                }));

                [weekRef, dayRef].forEach(ref => ref?.value?.control?.update());
                console.log('Portal: ячейки загружены');
            }
        })
        .catch(error => console.error('Ошибка портала:', error));
}

const handleScroll = () => {
    const st = window.pageYOffset || document.documentElement.scrollTop;

    // Если прокрутили вниз больше чем на 50px — скрываем
    // Если вернулись в самый верх — показываем
    if (st > 50) {
        isBtnVisible.value = false;
    } else {
        isBtnVisible.value = true;
    }
    lastScrollTop = st <= 0 ? 0 : st;
};

onMounted(async() => {
    window.addEventListener('scroll', handleScroll);
    // Запускаем все запросы одновременно и ждем их завершения
    try {
        await Promise.all([
            fetchWorkingHours(weekRef, dayRef),
            fetchEventCells(weekRef, dayRef),
            getAdditionalCellsPortal(),
            fetchAdditionalCells(weekRef, dayRef, false)
        ]);

        // Когда все данные в REF-ах, принудительно обновляем календарь
        [weekRef, dayRef].forEach(ref => ref?.value?.control?.update());
    } catch (e) {
        console.error("Ошибка при инициализации данных календаря", e);
    }
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
});
</script>

<template>
    <AuthenticatedLayout>
        <div class="py-4">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class=" shadow-sm sm:rounded-lg dark:bg-gray-800 hours-container">
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
                                    </div>
                                    <!-- Если пользователя нет, добавляем класс 'is-guest' -->
                                    <div class="calendar-wrapper" :class="{ 'is-guest': !$page.props.auth.user }">

                                        <Transition name="slide-up">
                                            <!-- Кнопка видна только если пользователь гость И не прокрутил страницу вниз -->
                                            <div v-if="!$page.props.auth.user && isBtnVisible" class="login-overlay-btn">
                                                <p>Хотите записаться?</p>
                                                <div class="auth-buttons">
                                                    <Link :href="route('login')" class="btn-login">Войти</Link>
                                                    <span class="divider">или</span>
                                                    <Link :href="route('register')" class="btn-register">Создать аккаунт</Link>
                                                </div>
                                            </div>
                                        </Transition>
                                    <DayPilotCalendar
                                        :config="config"
                                        :viewType="'Day'"
                                        :startDate="startDate"
                                        :visible="viewType === 'Day'"
                                        :events="events"
                                        @timeRangeSelected="config.onTimeRangeSelectedPortal"
                                        ref="dayRef"
                                    >
                                    </DayPilotCalendar>
                                    <DayPilotCalendar
                                        :config="config"
                                        :viewType="'Week'"
                                        :startDate="startDate"
                                        :visible="viewType === 'Week'"
                                        :events="events"
                                        :eventBorderRadius="5"
                                        :durationBarVisible="false"
                                        @timeRangeSelected="config.onTimeRangeSelectedPortal"
                                        @beforeCellRender="config.onBeforeCellRender"
                                        ref="weekRef"
                                    >
                                    </DayPilotCalendar>
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
        </div>
    </div>
    </AuthenticatedLayout>
</template>
