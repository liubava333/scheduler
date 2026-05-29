import { ref } from 'vue';
import axios from 'axios';
import { useForm, router, usePage } from '@inertiajs/vue3';

const page = usePage();
const events = ref([]);
export function useCalendarApi(weekRef: any) {
    const additionalCells = ref([]);
    const eventCells = ref([]);
    // Объект формы для настроек времени
    const form = useForm({
        weekdayStart: '08:00',
        weekdayEnd: '17:00',
        weekendStart: '09:00',
        weekendEnd: '15:00',
    });
    // 1. Получение настроек времени (только для админки или инициализации)
    const fetchWorkingHours = async (weekRef: any) => {
        try {
            const response = await axios.get(route('/api/dashboard.getHours'));
            const hours = response.data?.[0]; // Берем первую запись
            if (hours) {
                form.weekdayStart = hours.weekday_start;
                form.weekdayEnd = hours.weekday_end;
                form.weekendStart = hours.weekend_start;
                form.weekendEnd = hours.weekend_end;

                if (weekRef.value?.control) {
                    weekRef.value.control.update();
                }
            }
        } catch (error) {
            console.error('Ошибка загрузки часов:', error);
        }
    };

    const saveWorkingHours = (weekRef: any) => {
        // Очищаем старые ошибки перед новой проверкой
        form.clearErrors();

        let hasError = false;
        setTimeout(() => { // Небольшая задержка, чтобы Vue успел пересчитать классы
            if (form.weekdayEnd <= form.weekdayStart) {
                form.setError('weekdayEnd', 'Время окончания (будни) должно быть позже начала');
                hasError = true;
            }

            if (form.weekendEnd <= form.weekendStart) {
                form.setError('weekendEnd', 'Время окончания (выходные) должно быть позже начала');
                hasError = true;
            }
        }, 10);
        if (hasError) return; // Останавливаем отправку

        // 1. Клиентская проверка
        const isWeekdayValid = form.weekdayEnd > form.weekdayStart;
        const isWeekendValid = form.weekendEnd > form.weekendStart;

        if (!isWeekdayValid || !isWeekendValid) {
            // Выводим ошибку (можно заменить на ваш showMessage или alert)
            alert("Ошибка: Время окончания не может быть раньше или равно времени начала!");
            form.setError('weekdayEnd', 'Время окончания должно быть позже начала');
            return; // Прерываем отправку на сервер
        }

        // 2. Если всё ок — отправляем данные
        form.post(route('dashboard.store'), {
            preserveScroll: true,
            onSuccess: () => {
                if (weekRef.value?.control) {
                    weekRef.value?.control?.update();
                }

                console.log('График успешно сохранен');
            },
        });
    };

    // Метод для синхронизации событий из пропсов Inertia
    const fetchEvents = () => {
        // Извлекаем события из пропсов страницы
        const newEvents = page.props.events as any[];
        if (newEvents) {
            events.value = [...newEvents];
        }
    };
    // Опционально: метод для загрузки событий через Axios (если нужно без перезагрузки страницы)
    const loadEventsAsync = async () => {
        try {
            const response = await axios.get(route('events.getAll'));
            events.value = response.data;
        } catch (error) {
            console.error("Ошибка загрузки событий:", error);
        }
    };
    const fetchAdditionalCells = async (weekRef: any, isAdmin: boolean = false) => {
        try {
            const response = await axios.get(route('additional.getAll'));
            const data = response.data.additionalCells;

            if (data) {
                // Приводим все даты к формату DayPilot (с 'T') сразу при загрузке
                const cleanData = data.flat().map((c: any) => ({
                    ...c,
                    start: c.start.replace(" ", "T")
                }));

                if (isAdmin) {
                    // Для админа: объединяем без дублей
                    const combined = [...additionalCells.value, ...cleanData];
                    additionalCells.value = combined.filter((item, index, self) =>
                        index === self.findIndex((t) => t.start === item.start)
                    );
                } else {
                    // Для портала: просто заменяем актуальным списком
                    additionalCells.value = cleanData;
                }

                if (weekRef.value?.control) {
                    weekRef.value.control.update();
                }
            }
        } catch (error) {
            console.error('Ошибка загрузки ячеек:', error);
        }
    };

    const fetchEventCells = async (weekRef: any) => {
        const response = await axios.get(route('eventcells.getAll'));
        eventCells.value = response.data.eventCells; // Это спровоцирует перерисовку
    };
    // Универсальный метод сохранения ячеек события
    const handleSaveEvent = (formData: any, eventId: number | string) => {
        const cells = defineEventCells(formData.start, formData.end);
        router.post(route('eventcells.bulkStore'), {
            event_id: eventId,
            cells: cells
        }, {
            preserveState: true,
            onSuccess: async () => {
                console.log('The event cells have been successfully saved.');
                await fetchEvents();
                if (typeof fetchEventCells === 'function') {
                    await fetchEventCells();
                }

                if (weekRef?.value?.control) {
                    weekRef.value.control.update();
                }
            },
            onError: (err) => console.error("Error saving event cells:", err)
        });
    };

    const handleEditEvent = (oldFormData, formData, eventId) => {
        const cells = defineEventCells(formData.start, formData.end);
        router.post(route('eventcells.bulkStore'),  {event_id: eventId, cells: cells}, {
            preserveState: true,
            onSuccess: async () => {
                console.log('success edited Event');
                await fetchEvents();
                if (typeof fetchEventCells === 'function') {
                    await fetchEventCells(weekRef);
                }
                // Используем weekRef из замыкания useCalendarApi
                if (weekRef?.value?.control) {
                    weekRef.value.control.update();
                    console.log('Календарь обновлен успешно');
                } else {
                    console.error('Ошибка: calendarRef.value.control не найден', weekRef);
                }
            }
        });
    }
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
    return {
        events,
        form,
        additionalCells,
        eventCells,
        fetchEvents,
        loadEventsAsync,
        fetchWorkingHours,
        saveWorkingHours: () => saveWorkingHours(weekRef),
        fetchAdditionalCells: () => fetchAdditionalCells(weekRef),
        fetchEventCells: () => fetchEventCells(weekRef), // пробрасываем реф
        handleSaveEvent,
        handleEditEvent,
        defineEventCells
    };
}
