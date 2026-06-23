export type SampleStatus = 'pending' | 'ready' | 'failed';

export interface Sample {
    id: number;
    title: string;
    artist: string;
    status: SampleStatus;
    hasFileId: boolean;
    path: string;
}
