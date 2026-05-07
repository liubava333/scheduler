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
            IMask(el, { mask: '+38 (090) 000-00-00', lazy: false });
            clearInterval(interval);
        }
    }, 10);
    setTimeout(() => clearInterval(interval), 2000);
};

const open = async (data: any) => {
    const isEdit = !!(data?.modalData && data?.modalData.value.id);
    const modalData = data?.modalData?.value || data || props.initialData;
    currentModalData = modalData;

    document.addEventListener('click', handleGlobalClick);

    const selectedColor = COLORS.find(c => c.id === modalData.colorCustom) || { id: "#ccc", name: "Виберіть колір", color: "#ccc" };

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

    const formModal = [
        { name: "Name", id: "name", type: "text" },
        { name: "Phone", id: "phone", type: "text" },
        { name: "Date", id: "date", type: "date", disabled: !isEdit },
        { name: "Start", id: "start", type: "time", timeInterval: 30 },
        { name: "End", id: "end", type: "time", timeInterval: 30 },
        { name: "Note", id: "note", type: "textarea", height: 50 },
        { name: "Color", id: "colorCustom", html: colorDropdownHtml }
    ];

    initPhoneMask();
    const modal = await DayPilot.Modal.form(formModal, modalData);
    document.removeEventListener('click', handleGlobalClick);

    if (modal.canceled) return;

    const dateStr = new DayPilot.Date(modal.result.date).toString("yyyy-MM-dd");
    const params = {
        id: isEdit ? modalData.id : DayPilot.guid(),
        name: String(modal.result.name || ''),
        phone: String(modal.result.phone || ''),
        // Убеждаемся, что здесь только строки
        start: dateStr + "T" + String(modal.result.start),
        end: dateStr + "T" + String(modal.result.end),
        note: String(modal.result.note || ''),
        color: String(modal.result.colorCustom || ''),
    };
    if (isEdit) {
        router.patch(route('events.update', { id: params.id }), params, {
            onSuccess: () => {
                emit('update', modalData, params, params.id);
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
