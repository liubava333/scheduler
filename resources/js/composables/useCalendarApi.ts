import { ref } from 'vue';
import axios from 'axios';
import { useForm, router, usePage } from '@inertiajs/vue3';

const page = usePage();
const events = ref([]);
export function useCalendarApi(weekRef: any, dayRef: any) {
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
    const fetchWorkingHours = async (weekRef: any, dayRef: any) => {
        try {
            const response = await axios.get(route('/api/dashboard.getHours'));
            const hours = response.data?.[0]; // Берем первую запись
            if (hours) {
                form.weekdayStart = hours.weekday_start;
                form.weekdayEnd = hours.weekday_end;
                form.weekendStart = hours.weekend_start;
                form.weekendEnd = hours.weekend_end;

                [weekRef, dayRef].forEach(ref => ref?.value?.control?.update());
            }
        } catch (error) {
            console.error('Ошибка загрузки часов:', error);
        }
    };

    const saveWorkingHours = (weekRef: any, dayRef: any) => {
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
                [weekRef, dayRef].forEach(ref => ref?.value?.control?.update());
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
    const fetchAdditionalCells = async (weekRef: any, dayRef: any, isAdmin: boolean = false) => {
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

                [weekRef, dayRef].forEach(ref => ref?.value?.control?.update());
            }
        } catch (error) {
            console.error('Ошибка загрузки ячеек:', error);
        }
    };

    const fetchEventCells = async (weekRef: any, dayRef: any) => {
        try {
            const response = await axios.get(route('eventcells.getAll'));
            eventCells.value = response.data.eventCells; // Провоцирует перерисовку vue-стейта

            // Безопасный вызов обновления компонента DayPilot
            [weekRef, dayRef].forEach(ref => ref?.value?.control?.update());
        } catch (error) {
            console.error("Помилка оновлення ячеєк:", error);
        }
    };

    const handleSaveEvent = (formData: any, eventId: number | string) => {
        const cells = defineEventCells(formData.start, formData.end, formData.date);
        router.post(
            route('eventcells.bulkStore'),
            {
                event_id: eventId,
                cells: cells
            },
            {
                preserveState: true,
                onSuccess: (page) => {
                    console.log('The event cells have been successfully saved.', page);

                    // 1. Вызываем функции параллельно (они обновят ref-переменные в фоне)
                    fetchEvents();

                    if (typeof fetchEventCells === 'function') {
                        fetchEventCells(weekRef, dayRef);
                    }

                    // 2. Даем Vue мгновение на применение реактивности и обновляем визуальную часть календарей
                    setTimeout(() => {
                        [weekRef, dayRef].forEach(ref => ref?.value?.control?.update());
                    }, 0);
                },
                onError: (errors) => {
                    console.error("Error saving event cells:", errors);
                }
            }
        );
    };

    const handleEditEvent = (oldFormData: any, formData: any, eventId: number | string) => {

        const cells = defineEventCells(formData.start, formData.end, formData.date);
        router.post(
            route('eventcells.bulkStore'),
            { event_id: eventId, cells: cells },
            {
                preserveState: true,
                onSuccess: () => {
                    console.log('success edited Event');

                    // Просто вызываем функции параллельно
                    fetchEvents();
                    if (typeof fetchEventCells === 'function') {
                        fetchEventCells(weekRef, dayRef);
                    }

                    // Даем 0 миллисекунд для завершения микротасок Vue и обновляем календари
                    setTimeout(() => {
                        [weekRef, dayRef].forEach(ref => ref?.value?.control?.update());
                        console.log('Календари обновлены успешно');
                    }, 0);
                },
                onError: (errors) => {
                    console.error("Ошибка при сохранении ячеек события:", errors);
                }
            }
        );
    };

    const defineEventCells = (startCell, endCell, targetDate) => {
        // Функция для извлечения времени (HH:mm:ss) из исходной строки
        const getTimeString = (dateInput) => {
            const d = new Date(dateInput);
            const pad = (n) => n.toString().padStart(2, '0');
            return `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
        };

        // Создаем базовую дату (YYYY-MM-DD) из параметра targetDate
        const baseDate = new Date(targetDate);
        const pad = (n) => n.toString().padStart(2, '0');
        const datePart = `${baseDate.getFullYear()}-${pad(baseDate.getMonth() + 1)}-${pad(baseDate.getDate())}`;

        // Собираем новые объекты Date с нужной датой и оригинальным временем
        let current = new Date(`${datePart}T${getTimeString(startCell)}`);
        const end = new Date(`${datePart}T${getTimeString(endCell)}`);

        const cellArray = [];

        // Функция для форматирования итогового результата
        const formatDateTime = (date) => {
            return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
        };

        const diffInMinutes = (end - current) / (1000 * 60);
        if (diffInMinutes <= 30) {
            return [formatDateTime(current)];
        }

        while (current < end) {
            cellArray.push(formatDateTime(current));
            current.setMinutes(current.getMinutes() + 30);
        }

        return cellArray;
    };

    return {
        events,
        form,
        additionalCells,
        eventCells,
        fetchEvents,
        loadEventsAsync,
        fetchWorkingHours,
        saveWorkingHours: () => saveWorkingHours(weekRef, dayRef),
        fetchAdditionalCells: () => fetchAdditionalCells(weekRef, dayRef),
        fetchEventCells: () => fetchEventCells(weekRef, dayRef), // пробрасываем реф
        handleSaveEvent,
        handleEditEvent,
        defineEventCells
    };
}
