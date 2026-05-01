<script setup lang="ts">
import { DayPilot } from '@daypilot/daypilot-lite-vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    show: Boolean,
    initialData: Object
});

const emit = defineEmits(['close', 'save']);

// Функция, которую будет вызывать родитель через ref
const open = async (data) => {
// Используем данные, переданные в функцию, или props
    const modalData = data?.modalData?.value || props.initialData;
    initCustomDropdown(modalData);
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

// Генерируем HTML для выпадающего списка один раз
    const colorOptionsHtml = COLORS.map(c => `
    <div class="dropdown-option" data-value="${c.id}" style="padding:5px; cursor:pointer; display:flex; align-items:center;">
        <span style="background-color:${c.color}; width:30px; height:20px; display:inline-block; margin-right:10px;"></span>
        ${c.name}
    </div>
`).join('');

    const colorDropdownHtml = `
    <div class="custom-dropdown" style="position: relative;">
        <div id="selectedItem" style="border:1px solid #ccc; padding:5px; cursor:pointer; display:flex; align-items:center;">
            <span id="selectedColorBox" style="background-color:#ccc; width:30px; height:20px; display:inline-block; margin-right:10px;"></span>
            <span id="selectedText">Виберіть колір</span>
        </div>
        <div id="options" style="display:none; position:absolute; width:100%; max-height:200px; overflow-y:auto; background:white; border:1px solid #ccc; z-index:1000;">
            ${colorOptionsHtml}
        </div>
    </div>`;
    const formModal = [
        { name: "Name", id: "name", type: "text" },
        { name: "Phone", id: "phone", type: "text" },
        { name: "Date", id: "date", type: "date", disabled: true },
        { name: "Start", id: "start", type: "time", timeInterval: 30 },
        { name: "End", id: "end", type: "time", timeInterval: 30 },
        { name: "Note", id: "note", type: "textarea", height: 50 },
        { name: "Color", id: "colorCustom", html: colorDropdownHtml }
    ];
    // Вызываем модалку DayPilot
    const modal = await DayPilot.Modal.form(formModal, modalData);

    if (modal.canceled) return;

    const date = new DayPilot.Date(modal.result.date).toString("yyyy-MM-dd");
    const params = {
        name: modal.result.name,
        phone: modal.result.phone,
        start: date + "T" + modal.result.start,
        end: date + "T" + modal.result.end,
        id: DayPilot.guid(),
        note: modal.result.note,
        color: modal.result.colorCustom,
    };

    router.post(route('events.store'), params, {
        preserveState: true,
        onSuccess: (page) => {
            const eventId = page.props.flash.eventId;
            emit('save', params, eventId);
            emit('close');
        }
    });
};

const initCustomDropdown = (modalData) => {
    document.addEventListener('click', function(e) {
        const selectedItem = document.getElementById('selectedItem');
        const options = document.getElementById('options');
        if (e.target.closest('#selectedItem')) {
            options.style.display = options.style.display === 'none' ? 'block' : 'none';
        } else if (e.target.closest('.dropdown-option')) {
            const value = e.target.closest('.dropdown-option').getAttribute('data-value');
            selectedItem.innerHTML = e.target.closest('.dropdown-option').innerHTML;
            options.style.display = 'none';
            modalData.colorCustom = value;
        } else {
            if(options) options.style.display = 'none';
        }
    });
}
// Экспортируем метод наружу для родителя
defineExpose({ open });
</script>

<template>
</template>
