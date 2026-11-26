import { describe, expect, it } from 'vitest';
import { calculateOvertime, formatDuration, formatHours } from '@/lib/overtime';

// Kasus sama dengan tests/Feature/Services/OvertimeCalculatorTest.php (BUSINESS_RULES.md §7.1).
describe('calculateOvertime', () => {
    it.each([
        ['C1', '23:00', '02:00', 30, 150, true],
        ['C2', '17:00', '20:00', 0, 180, false],
        ['C3', '05:30', '07:45', 15, 120, false],
    ])(
        '%s: %s–%s istirahat %i → %i menit',
        (_, start, end, breakMinutes, total, crossesMidnight) => {
            expect(calculateOvertime(start, end, breakMinutes)).toEqual({
                ok: true,
                grossMinutes: total + breakMinutes,
                totalMinutes: total,
                crossesMidnight,
            });
        },
    );

    it.each([
        [
            'C4',
            '19:45',
            '20:15',
            30,
            'R3',
            'Istirahat harus lebih singkat dari lama lembur.',
        ],
        [
            'C5',
            '08:00',
            '08:00',
            0,
            'R2',
            'Jam selesai tidak boleh sama dengan jam mulai.',
        ],
        ['C6', '08:10', '10:00', 0, 'R1', 'Jam harus kelipatan 15 menit.'],
        [
            'C7',
            '06:00',
            '23:00',
            0,
            'R4',
            'Lama lembur melebihi batas wajar. Periksa jam mulai dan jam selesai.',
        ],
    ])('%s ditolak %s', (_, start, end, breakMinutes, rule, message) => {
        expect(calculateOvertime(start, end, breakMinutes)).toEqual({
            ok: false,
            rule,
            message,
        });
    });

    it('treats malformed times as R1', () => {
        expect(calculateOvertime('25:00', '10:00', 0)).toMatchObject({
            ok: false,
            rule: 'R1',
        });
    });

    it('uses the configured maximum gross duration', () => {
        expect(calculateOvertime('06:00', '22:00', 0).ok).toBe(true);
        expect(calculateOvertime('06:00', '22:00', 0, 900)).toMatchObject({
            ok: false,
            rule: 'R4',
        });
    });
});

describe('formatDuration', () => {
    it.each([
        [150, '2 jam 30 menit'],
        [180, '3 jam'],
        [45, '45 menit'],
        [0, '0 menit'],
    ])('%i → %s', (minutes, text) => {
        expect(formatDuration(minutes)).toBe(text);
    });
});

describe('formatHours', () => {
    it.each([
        [150, '2,5 jam'],
        [180, '3 jam'],
        [135, '2,25 jam'],
        [45, '0,75 jam'],
    ])('%i → %s', (minutes, text) => {
        expect(formatHours(minutes)).toBe(text);
    });
});
