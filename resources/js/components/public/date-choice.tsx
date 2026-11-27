import type { DateChoice } from '@/types/public-overtime';

export function DateChoiceField({
    id,
    choices,
    value,
    error,
    onChange,
}: {
    id: string;
    choices: DateChoice[];
    value?: DateChoice['value'];
    error?: string;
    onChange: (choice: DateChoice) => void;
}) {
    return (
        <fieldset
            aria-invalid={error ? true : undefined}
            aria-describedby={error ? `${id}-error` : undefined}
            className="min-w-0"
        >
            <legend className="mb-2 text-[15px] font-semibold">
                Tanggal lembur
            </legend>
            <div className="grid gap-2">
                {choices.map((choice, index) => (
                    <label
                        key={choice.date}
                        className="flex min-h-[52px] items-center gap-3 rounded-sm border border-line-control bg-surface px-3 py-2 text-[17px] has-checked:bg-primary-soft has-aria-invalid:border-danger"
                    >
                        <input
                            id={index === 0 ? id : `${id}-${choice.value}`}
                            name="date_choice"
                            type="radio"
                            aria-invalid={error ? true : undefined}
                            className="size-5 shrink-0 accent-primary"
                            checked={value === choice.value}
                            onChange={() => onChange(choice)}
                        />
                        {choice.label}
                    </label>
                ))}
            </div>
            {error && (
                <p id={`${id}-error`} className="mt-1 text-sm text-danger">
                    {error}
                </p>
            )}
        </fieldset>
    );
}
