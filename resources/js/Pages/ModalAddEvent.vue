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
