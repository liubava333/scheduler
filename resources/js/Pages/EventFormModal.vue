<script setup lang="ts">
import { DayPilot } from '@daypilot/daypilot-lite-vue';
import { router } from '@inertiajs/vue3';
import IMask from 'imask';

const props = defineProps({
    show: Boolean,
    initialData: Object
});

const emit = defineEmits(['close', 'save', 'update']);

let currentModalData: any = null;

const COLORS = [
    { id: "#FF0000", name: "Червоний", color: "red" },
    { id: "#008000", name: "Зелений", color: "green" },
    { id: "#FF69B4", name: "Світло рожевий", color: "hotPink" },
    { id: "#0000FF", name: "Синій", color: "blue" },
    { id: "#9acd32", name: "Жовто-зелений", color: "yellowGreen" },
    { id: "#3CB371", name: "Оливковий", color: "olive" },
    { id: "#CD5C5C", name: "Індійський червоний", color: "indianRed" },
    { id: "#367588", name: "Зеленувато-блакитний", color: "teal" },
    { id: "#ccff00", name: "Лайм", color: "lime" },
    { id: "#FF7F50", name: "Кораловий", color: "coral" },
    { id: "#800080", name: "Фіолетовий", color: "purple" },
    { id: "#FFA500", name: "Помаранчевий", color: "orange" },
    { id: "#F0E68C", name: "Хакі", color: "khaki" },
    { id: "#20B2AA", name: "Морський зелений", color: "seaGreen" },
    { id: "#FFFF00", name: "Жовтий", color: "yellow" },
    { id: "#7FFFD4", name: "Аквамарин", color: "aquamarine" },
    { id: "#FF00FF", name: "Фуксія", color: "fuchsia" },
    { id: "#FF1493", name: "Рожевий", color: "deepPink" },
    { id: "#4b4848", name: "Сірий", color: "gray" },
];

const handleGlobalClick = (e: MouseEvent) => {
    const options = document.getElementById('options');
    const selectedItem = document.getElementById('selectedItem');
    if (!options || !selectedItem) return;

    const target = e.target as HTMLElement;
    if (target.closest('#selectedItem')) {
        options.style.display = options.style.display === 'none' ? 'block' : 'none';
    } else if (target.closest('.dropdown-option')) {
        const option = target.closest('.dropdown-option') as HTMLElement;
        const value = option.getAttribute('data-value');
        selectedItem.innerHTML = option.innerHTML;
        options.style.display = 'none';
        if (currentModalData && value) currentModalData.colorCustom = value;
    } else {
        options.style.display = 'none';
    }
};

const initPhoneMask = () => {
    const interval = setInterval(() => {
        const el = document.querySelector('.modal_default_main input[name="phone"]') as HTMLInputElement;
        if (el) {
            IMask(el, { mask: '+38 (000) 000-00-00', lazy: false });
            clearInterval(interval);
        }
    }, 10);
    setTimeout(() => clearInterval(interval), 2000);
};

const open = async (data: any, validationContext = { eventCells: [], additionalCells: [] }) => {

    const isEdit = !!(data?.modalData && data?.modalData.value.id);

    // 1. Извлекаем "чистые" сырые данные без Vue-оберток
    let rawData = data?.modalData?.value || data || props.initialData;
    // Если это Ref, забираем его значение, иначе делаем глубокую копию
    rawData = JSON.parse(JSON.stringify(rawData));
    currentModalData = rawData;

    // Используем безопасный дефолт, если context не пришел
    const context = validationContext || { eventCells: [], additionalCells: [] };

    document.addEventListener('click', handleGlobalClick);

    const selectedColor = COLORS.find(c => c.id === rawData.colorCustom) || { id: "#ccc", name: "Виберіть колір", color: "#ccc" };

    const colorOptionsHtml = COLORS.map(c => `
        <div class="dropdown-option" data-value="${c.id}" style="padding:5px; cursor:pointer; display:flex; align-items:center;">
            <span style="background-color:${c.color}; border-radius: 3px; border: 1px solid rgba(0,0,0,0.2); width:30px; height:20px; display:inline-block; margin-right:10px;"></span>
            ${c.name}
        </div>`).join('');

    const colorDropdownHtml = `
        <div class="custom-dropdown" style="position: relative;">
            <div id="selectedItem" style="border:1px solid #ccc; padding:5px; cursor:pointer; display:flex; align-items:center;">
                <span style="background-color:${selectedColor.id}; width:30px; height:20px; display:inline-block; margin-right:10px; border-radius: 3px; border: 1px solid rgba(0,0,0,0.2);"></span>
                <span>${selectedColor.name}</span>
            </div>
            <div id="options" style="display:none; position:absolute; width:100%; max-height:200px; overflow-y:auto; background:white; border:1px solid #ccc; z-index:1000;">
                ${colorOptionsHtml}
            </div>
        </div>`;

    const generateAvailableTimeOptions = (selectedDateStr: string, context: any, currentSlot?: string, id?: any, isDateChanged = false) => {
        const options: { name: string, id: string }[] = [];
        const now = new Date();
        const todayStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
        const currentComputerMinutes = now.getHours() * 60 + now.getMinutes();

        const selectedDate = new Date(selectedDateStr);
        const dayOfWeek = selectedDate.getDay();
        const isWeekend = dayOfWeek === 0 || dayOfWeek === 6;

        const hours = context.workingHours || {};
        const startTime = isWeekend ? hours.weekendStart : hours.weekdayStart;
        const endTime = isWeekend ? hours.weekendEnd : hours.weekdayEnd;

        for (let hour = 0; hour < 24; hour++) {
            for (let min of ['00', '30']) {
                const timeStr = `${String(hour).padStart(2, '0')}:${min}`;
                const fullDateTimeStr = `${selectedDateStr}T${timeStr}:00`;

                const [slotH, slotM] = timeStr.split(':').map(Number);
                const slotMinutes = slotH * 60 + slotM;

                if (!isDateChanged && currentSlot && timeStr === currentSlot) {
                    options.push({ name: timeStr, id: timeStr });
                    continue;
                }

                // Жесткое отсечение прошлого для сегодняшнего дня по минутам (без багов TZ)
                if (selectedDateStr === todayStr && slotMinutes < currentComputerMinutes) {
                    continue;
                }

                if (!startTime || !endTime) continue;

                const isWorkingSlot = timeStr >= startTime && timeStr < endTime;
                const cellSetting = context.additionalCells?.find((e: any) => e.start === fullDateTimeStr);

                if (cellSetting) {
                    if (cellSetting.is_enabled === 0) continue;
                    if (!isWorkingSlot && cellSetting.is_enabled !== 1) continue;
                } else {
                    if (!isWorkingSlot) continue;
                }

                const isOccupied = context.eventCells?.some((e: any) => {
                    if (e.event_id == id) { return false; }
                    return e.start === fullDateTimeStr;
                });

                if (isOccupied) continue;

                options.push({ name: timeStr, id: timeStr });
            }
        }
        return options.sort((a, b) => a.id.localeCompare(b.id));
    };

    const getFilteredEndTimes = (
        startValue: string,
        allowedStartTimes: { id: string, name: string }[],
        currentEventTimeSlots: string[],
        selectedDateStr: string
    ) => {
        let allowedEndTimes: { name: string, id: string }[] = [];
        if (!startValue) return allowedEndTimes;

        const now = new Date();
        const todayStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
        const currentComputerMinutes = now.getHours() * 60 + now.getMinutes();

        const [startH, startM] = startValue.split(':').map(Number);
        const startMinutes = startH * 60 + startM;

        const allDaySlots: string[] = [];
        for (let h = 0; h < 24; h++) {
            for (let m of ['00', '30']) {
                allDaySlots.push(`${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`);
            }
        }
        allDaySlots.push("24:00");

        for (const slot of allDaySlots) {
            const [endH, endM] = slot.split(':').map(Number);
            const endMinutes = endH * 60 + endM;

            if (endMinutes <= startMinutes) continue;

            // Фильтр прошлого времени для Енда
            if (selectedDateStr === todayStr && endMinutes <= currentComputerMinutes) {
                continue;
            }

            const prevSlotMinutes = endMinutes - 30;
            const prevH = Math.floor(prevSlotMinutes / 60);
            const prevM = prevSlotMinutes % 60;
            const prevSlotStr = `${String(prevH).padStart(2, '0')}:${String(prevM).padStart(2, '0')}`;

            const isFreeSlot = allowedStartTimes.some(o => o.id === prevSlotStr);
            const isOwnCurrentSlot = currentEventTimeSlots && currentEventTimeSlots.includes(prevSlotStr);

            // ИСПРАВЛЕНИЕ: Заменено break на continue, чтобы не ломать селект при занятых слотах
            if (isFreeSlot || isOwnCurrentSlot) {
                allowedEndTimes.push({ name: slot, id: slot });
            } else {
                continue;
            }
        }
        return allowedEndTimes;
    };

    const getFormModalConfig = (targetDate, rawData, isEdit, defaultStartValue, context, currentEventTimeSlots) => {
        const allowedStartTimes = generateAvailableTimeOptions(targetDate, context, isEdit ? defaultStartValue : undefined, rawData.id);
        let allowedEndTimes: { name: string, id: string }[] = [];

        if (defaultStartValue) {
            allowedEndTimes = getFilteredEndTimes(defaultStartValue, allowedStartTimes, currentEventTimeSlots, targetDate);
        } else {
            allowedEndTimes = [...allowedStartTimes];
        }

        return [
            { name: "Ім'я", id: "name", type: "text",
                validate: (args) => {
                    const value = args.value ? args.value.trim() : "";
                    if (!value) {
                        args.valid = false;
                        args.message = `Полe "Ім'я" є обов'язковим для заповнення!`;
                    }
                }
            },
            { name: "Телефон", id: "phone", type: "text",
                validate: (args) => {
                    const value = args.value ? args.value.replace(/\D/g, "") : "";
                    if (!value) {
                        args.valid = false;
                        args.message = "Поле \"Номер телефону\" є обов'язковим для заповнення!";
                    } else if (value.length < 12) {
                        args.valid = false;
                        args.message = "Введіть правильний номер телефону!";
                    }
                }
            },
            { name: "Дата", id: "date", type: "date",
                validate: (args) => {
                    if (!args.value) {
                        args.valid = false;
                        args.message = "Поле \"Дата\" є обов'язковим для заповнення!";
                        return;
                    }
                    const selectedDateStr = new DayPilot.Date(args.value).toString("yyyy-MM-dd");
                    const now = new Date();
                    const todayStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;

                    if (selectedDateStr < todayStr) {
                        args.valid = false;
                        args.message = "Не можна обирати дату з минулого! Виберіть коректний день.";
                    }
                }
            },
            { name: "Початок", id: "start", type: "select", options: allowedStartTimes, value: defaultStartValue },
            { name: "Закінчення", id: "end", type: "select", options: allowedEndTimes, value: defaultEndValue,
                validate: (args) => {
                    const endSelectElement = document.querySelector('select[name="end"]') as HTMLSelectElement | null;
                    let effectiveEndValue = endSelectElement ? endSelectElement.value : args.value;
                    const startValue = args.result.start;
                    const endValue = effectiveEndValue;
                    if (startValue && endValue) {
                        const [startH, startM] = startValue.split(':').map(Number);
                        const [endH, endM] = endValue.split(':').map(Number);
                        if ((endH * 60 + endM) < (startH * 60 + startM + 30)) {
                            args.valid = false;
                            args.message = "Час закінчення має быть мінімум на 30 хвилин більшим за початок!";
                        }
                    }
                    args.value = endValue;
                    args.result.end = endValue;
                    externalFinalResult = { ...args.result, end: endValue };
                }
            },
            { name: "Note", id: "note", type: "textarea", height: 50 },
            { name: "Color", id: "colorCustom", html: colorDropdownHtml }
        ];
    };

    // ГЛОБАЛЬНЫЕ ТРЕКЕРЫ
    let externalFinalResult: any = null;
    let formModal: any[] = [];
    let lastProcessedDate = "";
    let globalActiveSlots: string[] = [];

    initPhoneMask();

    const refreshTimeSelects = (modalRoot: HTMLElement, selectedDateStr: string, updatedStartOptions: any[]) => {
        const allSelects = modalRoot.querySelectorAll("select");
        const startSelect = allSelects[0] as HTMLSelectElement | null;
        const endSelect = allSelects[1] as HTMLSelectElement | null;

        // Шаг А: Быстрая перерисовка СТАРТА через шаблонизатор строк
        if (startSelect) {
            const prevStart = startSelect.value;
            startSelect.innerHTML = updatedStartOptions.map(opt => `<option value="${opt.id}">${opt.name}</option>`).join('');

            defaultStartValue = updatedStartOptions.some(o => o.id === prevStart) ? prevStart : (updatedStartOptions[0]?.id || "09:00");
            startSelect.value = defaultStartValue;
        }

        // Шаг Б: Быстрая перерисовка ЭНДА и математический расчет +30 минут
        if (endSelect) {
            const newEndOptions = getFilteredEndTimes(defaultStartValue, updatedStartOptions, globalActiveSlots, selectedDateStr);
            endSelect.innerHTML = newEndOptions.map(opt => `<option value="${opt.id}">${opt.name}</option>`).join('');
            const [startH, startM] = defaultStartValue.split(':').map(Number);
            const targetMin = startH * 60 + startM + 30;
            const plus30Str = `${String(Math.floor(targetMin / 60)).padStart(2, '0')}:${String(targetMin % 60).padStart(2, '0')}`;

            if (newEndOptions.some(o => o.id === plus30Str)) {
                defaultEndValue = plus30Str;
            } else if (newEndOptions.length > 0) {
                defaultEndValue = newEndOptions[0].id;
            } else {
                defaultEndValue = plus30Str;
                endSelect.innerHTML += `<option value="${plus30Str}">${plus30Str}</option>`;
            }

            endSelect.value = defaultEndValue;
        }
    // Синхронизируем структуру массивов в памяти DayPilot
        const startField = formModal.find(item => item.id === "start");
        if (startField) {
            startField.options = updatedStartOptions;
        }

        const endField = formModal.find(item => item.id === "end");
        if (endField) {
            endField.options = formModal.find(item => item.id === "end")?.options || [];
        }
    }

    const options = {
        onChange: async (args) => {
            if (!args || !args.result || !args.result.date) return;

            const currentSelectedDate = args.result.date.split("T")[0];
            const todayStr = new Date().toISOString().split("T")[0];
            const modalRoot = args.root || document.querySelector(".modal_default_main") || document.body;

            // Если выбрана старая дата — очищаем списки
            if (currentSelectedDate < todayStr) {
                lastProcessedDate = currentSelectedDate;
                const allSelects = modalRoot.querySelectorAll("select");

                if (allSelects[0]) allSelects[0].innerHTML = "";
                if (allSelects[1]) allSelects[1].innerHTML = "";

                args.result.start = args.result.end = null;
                defaultStartValue = defaultEndValue = "";
                return;
            }

            if (currentSelectedDate !== lastProcessedDate) {
                lastProcessedDate = currentSelectedDate;
                const newStartOptions = generateAvailableTimeOptions(
                    currentSelectedDate,
                    context,
                    isEdit ? defaultStartValue : undefined,
                    rawData.id,
                    true
                );

                // Вызываем наше ядро оптимизации
                refreshTimeSelects(modalRoot, currentSelectedDate, newStartOptions);
                args.result.start = defaultStartValue;
                args.result.end = defaultEndValue;
            } else {
                if (args.result.start) defaultStartValue = args.result.start;
                if (args.result.end) defaultEndValue = args.result.end;
            }
        },

        onShow: (args) => {
            setTimeout(() => {
                const modalRoot = args.root || document.querySelector(".modal_default_main") || document.body;
                // Находим ваш кастомный элемент в DOM модального окна
                const customDropdown = modalRoot.querySelector(".custom-dropdown") as HTMLElement | null;

                if (customDropdown) {
                    if (rawData.isAdmin === false) {
                        // Находим всю строку формы (чтобы скрыть и название "Color", и сам дропдаун)
                        const formRow = customDropdown.closest(".modal_default_form_item") || customDropdown;
                        (formRow as HTMLElement).style.display = "none";
                    } else {
                        const formRow = customDropdown.closest(".modal_default_form_item") || customDropdown;
                        (formRow as HTMLElement).style.display = ""; // Возвращаем стандартное отображение
                    }
                }
                const allSelects = modalRoot.querySelectorAll("select");
                const startSelect = allSelects[0] as HTMLSelectElement | null;
                const endSelect = allSelects[1] as HTMLSelectElement | null;

                if (startSelect) startSelect.id = "dynamic-start-select";
                if (endSelect) endSelect.id = "dynamic-end-select";

                let cleanStart = rawData.start ? decodeURIComponent(rawData.start).trim() : "";
                if (cleanStart.includes("T")) {
                    cleanStart = cleanStart.split("T")[1].substring(0, 5);
                } else if (cleanStart.length > 5) {
                    cleanStart = cleanStart.substring(0, 5);
                }

                if (startSelect && cleanStart) startSelect.value = cleanStart;
                if (endSelect && defaultEndValue) endSelect.value = defaultEndValue;

                if (startSelect && endSelect) {
                    // Слушаем ручное изменение СТАРТА пользователем
                    startSelect.addEventListener('change', function(e: any) {
                        const startField = formModal.find(item => item.id === "start");
                        const activeStartTimes = startField ? startField.options : [];
                        const dateInput = modalRoot.querySelector('input[name="date"]') as HTMLInputElement | null;

                        // Вызываем ядро оптимизации при ручном клике
                        refreshTimeSelects(
                            modalRoot,
                            dateInput ? dateInput.value : lastProcessedDate,
                            activeStartTimes
                        );
                    });

                    // Слушаем изменение только ЭНДа
                    endSelect.addEventListener('change', function(e: any) {
                        defaultEndValue = e.target.value;
                    });
                }
            }, 0);
        }
    };

    // Извлекаем чистую дату (YYYY-MM-DD) для инициализации
    const initialDate = new DayPilot.Date(rawData.date || rawData.start).toString("yyyy-MM-dd");
    lastProcessedDate = initialDate; // Синхронизируем стартовую точку трекера

    // Вычисляем дефолтное значение СТАРТА (HH:mm) из rawData
    let defaultStartValue = "";
    if (rawData.start) {
        const cleanStart = decodeURIComponent(rawData.start).trim();
        if (cleanStart.includes("T")) {
            defaultStartValue = cleanStart.split("T")[1].substring(0, 5);
        } else if (cleanStart.length > 5) {
            defaultStartValue = cleanStart.substring(0, 5);
        } else {
            defaultStartValue = cleanStart;
        }
    }

    // Вычисляем дефолтное значение ОКОНЧАНИЯ (HH:mm) из rawData
    let defaultEndValue = "";
    if (rawData.end) {
        const cleanEnd = decodeURIComponent(rawData.end).trim();
        if (cleanEnd.includes("T")) {
            defaultEndValue = cleanEnd.split("T")[1].substring(0, 5);
        } else if (cleanEnd.length > 5) {
            defaultEndValue = cleanEnd.substring(0, 5);
        } else {
            defaultEndValue = cleanEnd;
        }
    }

    // Безопасное заполнение трекера слотов (без лишнего падающего кода)
    try {
        if (typeof currentEventTimeSlots !== 'undefined' && Array.isArray(currentEventTimeSlots)) {
            globalActiveSlots = currentEventTimeSlots;
        } else {
            globalActiveSlots = [];
        }
    } catch (e) {
        globalActiveSlots = []; // В случае ошибки оставляем массив безопасным и пустым
    }

    // Генерируем массив структуры полей формы
    formModal = getFormModalConfig(initialDate, rawData, isEdit, defaultStartValue, context, globalActiveSlots);

    // Открываем модальное окно DayPilot и ждем действий пользователя (await)
    const modal = await DayPilot.Modal.form(formModal, rawData, options);

    // Если пользователь нажал "Отмена" или кликнул мимо окна — прекращаем выполнение
    if (modal.canceled) return;

    // Извлекаем чистую финальную дату после закрытия окна
    const date = new DayPilot.Date(modal.result.date || modal.result.start || initialDate).toString("yyyy-MM-dd");

    // Удаляем глобальный слушатель кликов, если он использовался
    document.removeEventListener('click', handleGlobalClick);

    // Сборка значений времени из гарантированно обновляемых локальных трекеров
    const finalStart = modal.result.start || defaultStartValue;
    const finalEnd = modal.result.end || defaultEndValue;
    const randomColor = COLORS[Math.floor(Math.random() * COLORS.length)].id;

    // Формируем финальный идеальный JSON-объект параметров
    const params = {
        id: isEdit ? rawData.id : DayPilot.guid(),
        name: String(modal.result.name || ''),
        phone: String(modal.result.phone || ''),
        start: date + "T" + String(finalStart),
        end: date + "T" + String(finalEnd),
        note: String(modal.result.note || ''),
        color: String(modal.result.colorCustom || randomColor),
        date: date,
    };

    if (isEdit) {
        router.patch(route('events.update', { id: params.id }), params, {
            onSuccess: () => {
                emit('update', rawData, params, params.id);
                emit('close');
            }
        });
    } else {
        router.post(route('events.store'), params, {
            onSuccess: (page) => {
                emit('save', params, page.props.flash.eventId);
                emit('close');
            }
        });
    }
};

defineExpose({ open });
</script>
<template>

</template>
<style>
.modal_default_main .modal_default_form_item textarea, input {
    margin-left: 0px !important;
}
</style>
