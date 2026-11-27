import { clsx } from 'clsx';

// clsx, bukan clsx(): wordmark ikut dimuat form publik, dan tailwind-merge (~9 KB gzip)
// tidak perlu karena kelas di sini tidak saling menimpa.

type Variant = 'public' | 'sidebar' | 'login';

// Ukuran & warna sesuai DESIGN.md §8.1. Wordmark teks, bukan logo gambar.
const styles: Record<
    Variant,
    { mark: string; subtitle: string; text: string }
> = {
    public: {
        mark: 'text-[20px] text-ink',
        subtitle: 'text-sm font-medium text-ink-muted',
        text: 'Input Lembur · PT Kenco',
    },
    sidebar: {
        mark: 'text-base text-white',
        subtitle: 'text-xs text-sidebar-ink',
        text: 'PT Kenco',
    },
    login: {
        mark: 'text-[28px] leading-8 text-white',
        subtitle: 'text-sm text-sidebar-ink',
        text: 'Sistem Informasi Lembur · PT Kenco Manufaktur Indonesia',
    },
};

export function Wordmark({
    variant,
    className,
}: {
    variant: Variant;
    className?: string;
}) {
    const style = styles[variant];

    return (
        <div className={clsx('flex flex-col gap-0.5', className)}>
            <span className={clsx('font-bold tracking-[0.02em]', style.mark)}>
                SI-LEMBUR
            </span>
            <span className={style.subtitle}>{style.text}</span>
        </div>
    );
}
