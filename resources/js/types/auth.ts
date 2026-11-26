export type UserRole = 'ADMIN' | 'SUPERVISOR';

/** Data user yang dibagikan server lewat HandleInertiaRequests::sharedUser(). */
export type AuthUser = {
    id: number;
    name: string;
    username: string;
    role: UserRole;
    department: { id: number; name: string } | null;
};

export type Auth = {
    user: AuthUser | null;
};
