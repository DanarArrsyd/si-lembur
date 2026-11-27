import { formatDuration, MAX_BREAK_MINUTES, STEP_MINUTES } from './overtime';

const minutesPerDay = 24 * 60;

function formatTime(minutes: number): string {
    const hours = String(Math.floor(minutes / 60)).padStart(2, '0');
    const rest = String(minutes % 60).padStart(2, '0');

    return `${hours}:${rest}`;
}

export function timeOptions(): string[] {
    return Array.from({ length: minutesPerDay / STEP_MINUTES }, (_, index) =>
        formatTime(index * STEP_MINUTES),
    );
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

export function roundDownTime(time: string): string | null {
    const match = /^([01]\d|2[0-3]):([0-5]\d)$/.exec(time);

    if (!match) {
        return null;
    }

    const minutes = Number(match[1]) * 60 + Number(match[2]);

    return formatTime(minutes - (minutes % STEP_MINUTES));
}
