import type { Auth } from '@/types/auth';
import type { Translations } from '@/types/i18n';
import type { JoinedPool } from '@/types/navigation';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            timezone: string | null;
            locale: string;
            translations: Translations;
            sidebarOpen: boolean;
            joinedPools: JoinedPool[];
            hasLiveMatches: boolean;
            [key: string]: unknown;
        };
    }
}
