import api from './client';
import type { Sample } from '../types/sample';

interface PaginatedSamples {
    data: Sample[];
    meta: { page: number; per_page: number; total: number; total_pages: number };
}

const sampleApi = {
    list: (botId: number, params?: Record<string, unknown>) =>
        api.get<PaginatedSamples>(`/bots/${botId}/samples`, { params }),

    upload: (botId: number, data: FormData) =>
        api.post<{ data: Sample }>(`/bots/${botId}/samples`, data, {
            headers: { 'Content-Type': 'multipart/form-data' },
        }),

    delete: (botId: number, id: number) =>
        api.delete<{ status: string }>(`/bots/${botId}/samples/${id}`),

    retry: (botId: number, id: number) =>
        api.post<{ data: Sample }>(`/bots/${botId}/samples/${id}/retry`),
};

export default sampleApi;
