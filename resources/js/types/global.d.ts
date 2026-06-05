import type { Auth } from '@/types/auth';
import type { JoinedGame } from '@/types/navigation';

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
            sidebarOpen: boolean;
            joinedGames: JoinedGame[];
            [key: string]: unknown;
        };
    }
}
