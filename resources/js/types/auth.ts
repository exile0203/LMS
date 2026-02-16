export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    student_id_no?: string | null;
    role?: string;
    section?: string | null;
    course?: string | null;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
