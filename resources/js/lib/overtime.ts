/**
 * Mirror App\Services\OvertimeCalculator untuk preview di form (BUSINESS_RULES.md §2).
 * Hanya untuk tampilan: server tetap menghitung dan memvalidasi ulang.
 * Pesan harus sama persis dengan versi PHP.
 */

export type OvertimeRule = 'R1' | 'R2' | 'R3' | 'R4';

export type OvertimeCalculation =
    | {
          ok: true;
          grossMinutes: number;
          totalMinutes: number;
          crossesMidnight: boolean;
      }
    | { ok: false; rule: OvertimeRule; message: string };

const STEP_MINUTES = 15;
const MAX_BREAK_MINUTES = 240;
const DEFAULT_MAX_GROSS_MINUTES = 16 * 60;

const messages = {
    R1: 'Jam harus kelipatan 15 menit.',
    R2: 'Jam selesai tidak boleh sama dengan jam mulai.',
    R3: 'Istirahat harus lebih singkat dari lama lembur.',
    R4: 'Lama lembur melebihi batas wajar. Periksa jam mulai dan jam selesai.',
} as const satisfies Record<OvertimeRule, string>;

function fail(rule: OvertimeRule): OvertimeCalculation {
    return { ok: false, rule, message: messages[rule] };
}

/** "HH:MM" → menit sejak 00:00, atau null bila format/kelipatan salah. */
function parseTime(value: string): number | null {
    const match = /^([01]\d|2[0-3]):([0-5]\d)$/.exec(value);

    if (!match) {
        return null;
    }

    const minutes = Number(match[1]) * 60 + Number(match[2]);

    return minutes % STEP_MINUTES === 0 ? minutes : null;
}

export function calculateOvertime(
    start: string,
    end: string,
    breakMinutes: number,
    maxGrossMinutes: number = DEFAULT_MAX_GROSS_MINUTES,
): OvertimeCalculation {
    const startMinute = parseTime(start);
    const endMinute = parseTime(end);

    if (
        startMinute === null ||
        endMinute === null ||
        breakMinutes < 0 ||
        breakMinutes % STEP_MINUTES !== 0 ||
        breakMinutes > MAX_BREAK_MINUTES
    ) {
        return fail('R1');
    }

    if (startMinute === endMinute) {
        return fail('R2');
    }

    const crossesMidnight = endMinute < startMinute;
    const grossMinutes =
        (crossesMidnight ? endMinute + 24 * 60 : endMinute) - startMinute;

    if (grossMinutes > maxGrossMinutes) {
        return fail('R4');
    }

    if (breakMinutes >= grossMinutes) {
        return fail('R3');
    }

    return {
        ok: true,
        grossMinutes,
        totalMinutes: grossMinutes - breakMinutes,
        crossesMidnight,
    };
}

/** 150 → "2 jam 30 menit", 180 → "3 jam", 45 → "45 menit". */
export function formatDuration(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    if (hours === 0) {
        return `${rest} menit`;
    }

    return rest === 0 ? `${hours} jam` : `${hours} jam ${rest} menit`;
}

/** 150 → "2,5 jam", 135 → "2,25 jam" (koma desimal Indonesia, tanpa nol di belakang). */
export function formatHours(minutes: number): string {
    const hours = Math.round((minutes / 60) * 100) / 100;

    return `${String(hours).replace('.', ',')} jam`;
}
