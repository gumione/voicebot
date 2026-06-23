export interface Bot {
    id: number;
    name: string;
    username: string;
    hasToken: boolean;
    storageChatId: string | null;
    webhookToken: string;
    isActive: boolean;
    sampleCount: number;
    createdAt: string;
}

export interface BotPayload {
    name: string;
    username: string;
    token: string;
    storageChatId?: string;
    isActive?: boolean;
}
