import {
    formatDuration,
    MAX_BREAK_MINUTES,
    STEP_MINUTES,
} from '@/lib/overtime';

export function timeOptions(): string[] {
    return Array.from({ length: (24 * 60) / STEP_MINUTES }, (_, index) => {
        const minutes = index * STEP_MINUTES;
        const hours = String(Math.floor(minutes / 60)).padStart(2, '0');
        const rest = String(minutes % 60).padStart(2, '0');

        return `${hours}:${rest}`;
    });
}

export function breakOptions(): { value: number; label: string }[] {
    return Array.from(
        { length: MAX_BREAK_MINUTES / STEP_MINUTES + 1 },
        (_, index) => {
            const value = index * STEP_MINUTES;

            return { value, label: formatDuration(value) };
        },
    );
}

export function roundTimeDown(time: string): string {
    const minutes = Number(time.slice(3));
    const rounded = Math.floor(minutes / STEP_MINUTES) * STEP_MINUTES;

    return `${time.slice(0, 3)}${String(rounded).padStart(2, '0')}`;
}
