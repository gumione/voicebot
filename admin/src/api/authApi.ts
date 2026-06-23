import api from './client';

export interface LoginRequest {
    email: string;
    password: string;
}

// Token is at the top level of the login response, NOT under `data`.
export interface LoginResponse {
    token: string;
}

export interface MeUser {
    id: number;
    email: string;
    roles: string[];
}

const authApi = {
    login: (data: LoginRequest) =>
        api.post<LoginResponse>('/login', data),

    me: () =>
        api.get<{ data: MeUser }>('/me'),
};

export default authApi;
