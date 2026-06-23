import api from './client';
import type { Bot, BotPayload } from '../types/bot';

interface PaginatedBots {
    data: Bot[];
    meta: { page: number; per_page: number; total: number; total_pages: number };
}

const botApi = {
    list: (params?: Record<string, unknown>) =>
        api.get<PaginatedBots>('/bots', { params }),

    getById: (id: number) =>
        api.get<{ data: Bot }>(`/bots/${id}`),

    create: (data: BotPayload) =>
        api.post<{ data: Bot }>('/bots', data),

    update: (id: number, data: Partial<BotPayload>) =>
        api.put<{ data: Bot }>(`/bots/${id}`, data),

    delete: (id: number) =>
        api.delete<{ status: string }>(`/bots/${id}`),
};

export default botApi;
