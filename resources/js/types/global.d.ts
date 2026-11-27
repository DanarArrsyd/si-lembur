import type { Auth } from '@/types/auth';
import type { OvertimeReview } from '@/types/public-overtime';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        flashDataType: {
            review?: OvertimeReview;
            [key: string]: unknown;
        };
        sharedPageProps: {
            name: string;
            auth: Auth;
            [key: string]: unknown;
        };
    }
}
