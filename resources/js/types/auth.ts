export type UserRole = 'STUDENT' | 'TEACHER' | 'ADMIN';

export type User = {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    role: UserRole;
    phone: string | null;
    birth_date: string | null;
    created_at: string;
    updated_at: string;
    avatar?: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};
