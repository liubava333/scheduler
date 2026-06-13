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
    { id: "#808080", name: "Сірий", color: "gray" },
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

    const generateAvailableTimeOptions = (selectedDateStr: string, context: any, currentSlot?: string) => {
        const options: { name: string, id: string }[] = [];
        const now = new Date();

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
                const checkDate = new Date(fullDateTimeStr);

                // Если это текущее редактируемое время,
                // мы пропускаем любые проверки на занятость/прошлое время и сразу добавляем его
                if (currentSlot && timeStr === currentSlot) {
                    options.push({ name: timeStr, id: timeStr });
                    continue;
                }

                // Ниже идет ваша стандартная логика проверок для остальных слотов
                if (checkDate < now) continue;
                if (!startTime || !endTime) continue;

                const isWorkingSlot = timeStr >= startTime && timeStr < endTime;
                const cellSetting = context.additionalCells?.find((e: any) => e.start === fullDateTimeStr);

                if (cellSetting) {
                    if (cellSetting.is_enabled === 0) continue;
                    if (!isWorkingSlot && cellSetting.is_enabled !== 1) continue;
                } else {
                    if (!isWorkingSlot) continue;
                }

                const isOccupied = context.eventCells?.some((e: any) => e.start === fullDateTimeStr);
                if (isOccupied) continue;

                options.push({ name: timeStr, id: timeStr });
            }
        }

        // Сортируем массив, если принудительно добавленный слот нарушил хронологический порядок
        return options.sort((a, b) => a.id.localeCompare(b.id));
    };

// Вспомогательная функция для генерации списка END на основе выбранного СТАРТА
// Выносим её отдельно, чтобы вызывать и при инициализации, и при изменении (onchange)
    const getFilteredEndTimes = (startTimeStr: string, allowedStartTimes: { id: string }[]) => {
        let filteredEndTimes: { name: string, id: string }[] = [];
        if (!startTimeStr) return allowedStartTimes;

        const [startH, startM] = startTimeStr.split(':').map(Number);
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

            const prevSlotMinutes = endMinutes - 30;
            const prevH = Math.floor(prevSlotMinutes / 60);
            const prevM = prevSlotMinutes % 60;
            const prevSlotStr = `${String(prevH).padStart(2, '0')}:${String(prevM).padStart(2, '0')}`;

            if (allowedStartTimes.some(o => o.id === prevSlotStr)) {
                filteredEndTimes.push({ name: slot, id: slot });
            } else {
                break; // Наткнулись на недоступную ячейку — прерываем цепочку
            }
        }
        return filteredEndTimes;
    };

    // 1. Извлекаем чистые данные из rawData
    let rawStartStr = rawData.start ? decodeURIComponent(String(rawData.start)).trim() : "";
    let rawEndStr = rawData.end ? decodeURIComponent(String(rawData.end)).trim() : "";

    let dateStr = new DayPilot.Date(rawData.date || rawData.start).toString("yyyy-MM-dd");

    // Вытаскиваем чистое время HH:mm ("10:30")
    const defaultStartValue = rawStartStr.includes("T") ? rawStartStr.split("T")[1].substring(0, 5) : rawStartStr.substring(0, 5);
    const defaultEndValue = rawEndStr.includes("T") ? rawEndStr.split("T")[1].substring(0, 5) : rawEndStr.substring(0, 5);

    // Перезаписываем в rawData очищенные форматы
    rawData.start = defaultStartValue;
    rawData.end = defaultEndValue;

    // Генерируем базовый список разрешенных ячеек
    const allowedStartTimes = generateAvailableTimeOptions(dateStr, context, isEdit ? defaultStartValue : undefined);

    // ВЫЧИСЛЯЕМ СЛОТЫ ТЕКУЩЕГО СОБЫТИЯ НАПРЯМУЮ (Замена defineEventCells)
    const currentEventTimeSlots: string[] = [];
    if (isEdit && rawStartStr.includes("T") && rawEndStr.includes("T")) {
        let current = new Date(rawStartStr);
        const end = new Date(rawEndStr);
        while (current < end) {
            const pad = (n: number) => n.toString().padStart(2, '0');
            currentEventTimeSlots.push(`${pad(current.getHours())}:${pad(current.getMinutes())}`);
            current.setMinutes(current.getMinutes() + 30);
        }
    }

    // Логика фильтрации для поля END
    let allowedEndTimes: { name: string, id: string }[] = [];

    if (defaultStartValue) {
        const [startH, startM] = defaultStartValue.split(':').map(Number);
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

            const prevSlotMinutes = endMinutes - 30;
            const prevH = Math.floor(prevSlotMinutes / 60);
            const prevM = prevSlotMinutes % 60;
            const prevSlotStr = `${String(prevH).padStart(2, '0')}:${String(prevM).padStart(2, '0')}`;

            // Проверяем: либо слот свободен, либо он принадлежит текущему редактируемому событию
            const isFreeSlot = allowedStartTimes.some(o => o.id === prevSlotStr);
            const isOwnCurrentSlot = currentEventTimeSlots.includes(prevSlotStr);

            if (isFreeSlot || isOwnCurrentSlot) {
                allowedEndTimes.push({ name: slot, id: slot });
            } else {
                break; // Наткнулись на чужой блок — прерываем добавление
            }
        }
    } else {
        allowedEndTimes = [...allowedStartTimes];
    }

    // 1. Создаем внешнюю переменную для перехвата данных (замыкание)
    let externalFinalResult: any = null;
    const formModal = [
        { name: "Ім'я", id: "name", type: "text",
            validate: (args) => {
                const value = args.value ? args.value.trim() : "";

                if (!value) {
                    args.valid = false; // Блокируем отправку формы
                    args.message = `Полe "Ім'я" є обов'язковим для заповнення!`; // Выводим ошибку
                }
            }
        },
        { name: "Телефон", id: "phone", type: "text",
            validate: (args) => {
                const value = args.value ? args.value.replace(/\D/g, "") : ""; // Удаляем все не-цифры

                if (!value) {
                    args.valid = false;
                    args.message = "Поле \"Номер телефону\" є обов'язковим для заповнення!";
                } else if (value.length < 12) { // Например, проверка на минимальную длину номера
                    args.valid = false;
                    args.message = "Введіть правильний номер телефону!";
                }
            }
        },
        { name: "Дата", id: "date", type: "date", disabled: !isEdit },
        { name: "Початок", id: "start",  type: "select", options: allowedStartTimes },
        { name: "Закінчення", id: "end",  type: "select", options: allowedEndTimes,
            validate: (args) => {
                // Жёстко вытаскиваем элемент из модального окна по его ID/Name
                const endSelectElement = document.querySelector('select[name="end"]') as HTMLSelectElement | null;
                // Если DayPilot потерял значение, но в DOM-селекте оно физически выбрано — восстанавливаем его в стейт
                if (endSelectElement) {
                    args.result.end = endSelectElement.value;
                    // Перезаписываем локальную переменную для выполнения валидации ниже
                    var effectiveEndValue = endSelectElement.value;
                } else {
                    var effectiveEndValue = args.value;
                }
                const startValue = args.result.start;
                const endValue = effectiveEndValue;
                if (startValue && endValue) {
                    const [startH, startM] = startValue.split(':').map(Number);
                    const [endH, endM] = endValue.split(':').map(Number);

                    const startMinutes = startH * 60 + startM;
                    const endMinutes = endH * 60 + endM;

                    if (endMinutes < startMinutes + 30) {
                        args.valid = false; // Блокируем отправку
                        args.message = "Час закінчення має бути мінімум на 30 хвилин більшим за початок!";
                    }
                }
                args.value = endValue
                args.result.end = endValue;
                // Собираем финальный объект результата со всеми заполненными полями формы
                externalFinalResult = {
                    ...args.result,
                    end: endValue // Принудительно вшиваем корректный End
                };
            }
        },
        { name: "Note", id: "note", type: "textarea", height: 50 },
        { name: "Color", id: "colorCustom", html: colorDropdownHtml }
    ];

    initPhoneMask();
    const options = {
        onShow: (args) => {
            setTimeout(() => {
                // Находим нативные селекты в DOM по их именам
                const startSelect = args.root.querySelector('select[name="start"]') as HTMLSelectElement | null;
                const endSelect = args.root.querySelector('select[name="end"]') as HTMLSelectElement | null;
                let cleanStart = rawData.start ? decodeURIComponent(rawData.start).trim() : "";
                if (cleanStart.includes("T")) {
                    cleanStart = cleanStart.split("T")[1].substring(0, 5); // Получим "10:30"
                } else if (cleanStart.length > 5) {
                    cleanStart = cleanStart.substring(0, 5); // Если пришло "10:30:00", обрезаем до "10:30"
                }
                if (startSelect && cleanStart) {
                    startSelect.value = cleanStart;
                }
                if (endSelect && defaultEndValue) {
                    endSelect.value = defaultEndValue;
                }

                if (startSelect && endSelect) {
                    startSelect.addEventListener('change', function(e: any) {
                        const selectedStart = e.target.value;

                        // Пересчитываем массив разрешенных временных точек для ЭНД на основе новой строки старта
                        const updatedEndOptions = getFilteredEndTimes(selectedStart, allowedStartTimes);
                        const currentEndValue = endSelect.value;

                        // Полностью очищаем старые option из селекта End в DOM
                        while (endSelect.options.length > updatedEndOptions.length) {
                            endSelect.remove(endSelect.options.length - 1);
                        }

                        // Обновляем текущие или создаем строго новые по индексу
                        updatedEndOptions.forEach((opt, index) => {
                            let optionElement = endSelect.options[index];
                            if (!optionElement) {
                                optionElement = document.createElement('option');
                                endSelect.add(optionElement);
                            }
                            optionElement.value = opt.id;
                            optionElement.text = opt.name;
                        });
                        let newEndValue = ''
                        const isStillValid = updatedEndOptions.some(o => o.id === currentEndValue);
                        if (isStillValid) {
                            newEndValue = currentEndValue; // Оставляем старый выбор пользователя
                        } else if (updatedEndOptions.length > 0) {
                            newEndValue= updatedEndOptions[0].id; // Или принудительно ставим минимальный доступный (+30 минут)
                        }
                        // Записываем значение напрямую в DOM элемент
                        endSelect.value = newEndValue;
                        const event = document.createEvent('HTMLEvents');
                        event.initEvent('change', true, true);
                        endSelect.dispatchEvent(event);
                    });
                }
            }, 0);
        }
    };
    const modal = await DayPilot.Modal.form(formModal, rawData, options);
    document.removeEventListener('click', handleGlobalClick);

    if (modal.canceled) return;

    const params = {
        id: isEdit ? rawData.id : DayPilot.guid(),
        name: String(modal.result.name || ''),
        phone: String(modal.result.phone || ''),
        // Убеждаемся, что здесь только строки
        start: dateStr + "T" + String(modal.result.start),
        end: dateStr + "T" + String(externalFinalResult?.end),
        note: String(modal.result.note || ''),
        color: String(modal.result.colorCustom || ''),
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
