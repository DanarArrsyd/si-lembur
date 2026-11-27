import { expect, it } from 'vitest';
import { breakOptions, timeOptions } from './time-options';

it('generates all 96 quarter-hour times in order', () => {
    const options = timeOptions();

    expect(options).toHaveLength(96);
    expect(new Set(options)).toHaveProperty('size', 96);
    expect(options.slice(0, 5)).toEqual([
        '00:00',
        '00:15',
        '00:30',
        '00:45',
        '01:00',
    ]);
    expect(options.at(-1)).toBe('23:45');
});

it('generates breaks from zero minutes through four hours', () => {
    const options = breakOptions();

    expect(options.map(({ value }) => value)).toEqual(
        Array.from({ length: 17 }, (_, index) => index * 15),
    );
    expect(options[0]).toEqual({ value: 0, label: '0 menit' });
    expect(options[5]).toEqual({ value: 75, label: '1 jam 15 menit' });
    expect(options[16]).toEqual({ value: 240, label: '4 jam' });
});
