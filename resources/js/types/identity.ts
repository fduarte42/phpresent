export interface User {
    id: string;
    email: string;
    displayName: string;
    roleIds: string[];
    isActive: boolean;
    createdAt: string;
}

export interface Role {
    id: string;
    name: string;
    permissions: string[];
}
