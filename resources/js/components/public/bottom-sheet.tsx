import * as Dialog from '@radix-ui/react-dialog';
import type { ComponentProps, ReactElement, ReactNode } from 'react';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    trigger: ReactElement;
    onOpenAutoFocus?: ComponentProps<typeof Dialog.Content>['onOpenAutoFocus'];
    children: ReactNode;
};

export function BottomSheet({
    open,
    onOpenChange,
    title,
    trigger,
    onOpenAutoFocus,
    children,
}: Props) {
    return (
        <Dialog.Root open={open} onOpenChange={onOpenChange}>
            <Dialog.Trigger asChild>{trigger}</Dialog.Trigger>
            <Dialog.Portal>
                <Dialog.Overlay className="fixed inset-0 z-50 bg-[rgba(15,30,46,0.4)]" />
                <Dialog.Content
                    aria-describedby={undefined}
                    onOpenAutoFocus={onOpenAutoFocus}
                    className="fixed inset-x-0 bottom-0 z-50 mx-auto flex max-h-[min(78dvh,28rem)] w-full max-w-[480px] flex-col rounded-t-md border border-line bg-surface pb-[env(safe-area-inset-bottom)] shadow-[var(--shadow-float)]"
                >
                    <div className="flex items-center justify-between gap-2 border-b border-line px-4 py-1">
                        <Dialog.Title className="text-base font-semibold">
                            {title}
                        </Dialog.Title>
                        <Dialog.Close className="min-h-12 shrink-0 rounded-md px-3 text-sm font-semibold text-ink hover:bg-surface-sunken">
                            Tutup
                        </Dialog.Close>
                    </div>
                    {children}
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}
