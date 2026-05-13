import { DayPilot } from '@daypilot/daypilot-lite-vue';
import { ref } from 'vue';

export function useCalendarShared() {

    const colors = {
        // enabled: "#d1e3ff",
        enabled: "rgb(209, 227, 255, 0.73)",
        notEnabled: "#f0f0f0",
        occupied: "#f0f0f0"
    };
    const calendarMessage = ref("");

    const showMessage = (text: string) => {
        calendarMessage.value = text;
        setTimeout(() => {
            calendarMessage.value = "";
        }, 3000);
    };
    const setupCellRender = (args: any, { additionalCells, eventCells, form, isAdmin = false }) => {
        const cellValue = args.cell.start.value;
        const cellDate = args.cell.start;
        const now = DayPilot.Date.now();

        // Обработка прошлого
        if (cellDate < now) {
            args.cell.properties.backColor = colors.notEnabled;
            args.cell.properties.cursor = "not-allowed";
            return;
        }

        // Базовая логика рабочего времени
        const dayOfWeek = cellDate.getDayOfWeek();
        const hour = cellDate.toString("HH:mm");
        const isWeekend = (dayOfWeek === 0 || dayOfWeek === 6);
        const startLimit = isWeekend ? form.weekendStart : form.weekdayStart;
        const endLimit = isWeekend ? form.weekendEnd : form.weekdayEnd;

        let isWorking = (hour >= startLimit && hour < endLimit);

        // Исключения (Additional Cells)
        if (additionalCells.value) {
            const override = additionalCells.value.find((e: any) => e.start.replace(" ", "T") === cellValue);
            if (override) isWorking = !!override.is_enabled;
        }

        const isOccupied = eventCells.value?.some((e: any) => {
            return e.start === cellValue;
        });

        // Рендеринг
        if (isOccupied) {
            args.cell.properties.backColor = colors.occupied;
            args.cell.properties.html = '';
        } else {
            args.cell.properties.backColor = isWorking ? colors.enabled : colors.notEnabled;

            // Показываем иконки только если это админ
            if (isAdmin) {
                const badgeIcon = isWorking ? "x" : "+";
                const badgeType = isWorking ? "icon-remove" : "icon-plus";
                args.cell.properties.html = `
                    <div class="cell-badge-container">
                        <div class="badge" data-info="${badgeType}">${badgeIcon}</div>
                    </div>
                `;
            }
        }
    };
    const getCellStatus = (start, additionalCells, form) => {
        const cellValue = start.value;
        const dayOfWeek = start.getDayOfWeek();
        const hour = start.toString("HH:mm");
        const isWeekend = (dayOfWeek === 0 || dayOfWeek === 6);

        const startLimit = isWeekend ? form.weekendStart : form.weekdayStart;
        const endLimit = isWeekend ? form.weekendEnd : form.weekdayEnd;

        let isWorking = (hour >= startLimit && hour < endLimit);

        if (additionalCells.value) {
            const override = additionalCells.value.find(e => e.start.replace(" ", "T") === cellValue);
            if (override) isWorking = !!override.is_enabled;
        }
        return { isWorking, cellValue };
    };
    return { setupCellRender, getCellStatus, calendarMessage,
        showMessage, };
}
