import * as Dialog from '@radix-ui/react-dialog';
import type { ComponentProps, ReactNode } from 'react';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    trigger: ReactNode;
    children: ReactNode;
    onOpenAutoFocus?: ComponentProps<typeof Dialog.Content>['onOpenAutoFocus'];
};

export function BottomSheet({
    open,
    onOpenChange,
    title,
    trigger,
    children,
    onOpenAutoFocus,
}: Props) {
    return (
        <Dialog.Root open={open} onOpenChange={onOpenChange}>
            <Dialog.Trigger asChild>{trigger}</Dialog.Trigger>
            <Dialog.Portal>
                <Dialog.Overlay
                    className="fixed inset-0 z-50 bg-[rgba(15,30,46,0.4)]"
                    data-testid="bottom-sheet-overlay"
                />
                <Dialog.Content
                    aria-describedby={undefined}
                    className="fixed inset-x-0 bottom-0 z-50 mx-auto flex max-h-[85dvh] max-w-[480px] flex-col rounded-t-[6px] bg-surface pb-[env(safe-area-inset-bottom)] shadow-[var(--shadow-float)]"
                    onOpenAutoFocus={onOpenAutoFocus}
                >
                    <div className="flex shrink-0 items-center justify-between gap-4 border-b border-line px-4">
                        <Dialog.Title className="text-[17px] font-semibold text-ink">
                            {title}
                        </Dialog.Title>
                        <Dialog.Close
                            type="button"
                            className="min-h-12 min-w-12 rounded-sm px-2 text-sm font-semibold text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                        >
                            Tutup
                        </Dialog.Close>
                    </div>
                    {children}
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}
