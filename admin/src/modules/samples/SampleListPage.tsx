import { useState, useCallback } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useDropzone } from 'react-dropzone';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'react-toastify';
import { FiArrowLeft, FiUploadCloud, FiRefreshCw, FiTrash2, FiCheckCircle, FiXCircle } from 'react-icons/fi';
import sampleApi from '../../api/sampleApi';
import botApi from '../../api/botApi';
import DataGrid, { type Column } from '../../shared/DataGrid';
import type { Sample, SampleStatus } from '../../types/sample';

const errMessage = (err: unknown) =>
    (err as { response?: { data?: { message?: string } } }).response?.data?.message;

const STATUS_STYLES: Record<SampleStatus, string> = {
    pending: 'bg-warning/10 text-warning',
    ready: 'bg-success/10 text-success',
    failed: 'bg-error/10 text-error',
};

const SampleListPage = () => {
    const { id } = useParams();
    const botId = Number(id);
    const navigate = useNavigate();
    const queryClient = useQueryClient();

    const [batchArtist, setBatchArtist] = useState('');
    const [isUploading, setIsUploading] = useState(false);

    const gridKey = ['samples-grid', botId];
    const invalidate = () => queryClient.invalidateQueries({ queryKey: ['samples-grid', botId] });

    const { data: bot } = useQuery({
        queryKey: ['bot', id],
        queryFn: () => botApi.getById(botId).then((r) => r.data.data),
    });

    const onDrop = useCallback(async (acceptedFiles: File[]) => {
        if (!acceptedFiles.length) return;
        setIsUploading(true);
        let ok = 0;
        let failed = 0;
        for (const file of acceptedFiles) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('title', file.name.replace(/\.[^.]+$/, ''));
            if (batchArtist.trim()) formData.append('artist', batchArtist.trim());
            try {
                await sampleApi.upload(botId, formData);
                ok++;
            } catch (err) {
                failed++;
                toast.error(`${file.name}: ${errMessage(err) || 'upload failed'}`);
            }
        }
        setIsUploading(false);
        if (ok) toast.success(`Uploaded ${ok} sample${ok > 1 ? 's' : ''}`);
        if (ok || failed) invalidate();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [botId, batchArtist]);

    const { getRootProps, getInputProps, isDragActive } = useDropzone({
        onDrop,
        accept: {
            'audio/mpeg': ['.mp3'],
            'audio/wav': ['.wav'],
            'audio/x-wav': ['.wav'],
            'audio/mp4': ['.m4a'],
            'audio/x-m4a': ['.m4a'],
            'audio/ogg': ['.ogg'],
        },
        disabled: isUploading,
    });

    const retryMutation = useMutation({
        mutationFn: (sampleId: number) => sampleApi.retry(botId, sampleId),
        onSuccess: () => {
            invalidate();
            toast.success('Retry queued');
        },
        onError: (err) => toast.error(errMessage(err) || 'Error'),
    });

    const deleteMutation = useMutation({
        mutationFn: (sampleId: number) => sampleApi.delete(botId, sampleId),
        onSuccess: () => {
            invalidate();
            toast.success('Sample deleted');
        },
        onError: (err) => toast.error(errMessage(err) || 'Error'),
    });

    const columns: Column<Sample>[] = [
        {
            key: 'title',
            header: 'Title',
            render: (row) => <span className="font-medium text-dark">{row.title || '—'}</span>,
        },
        {
            key: 'artist',
            header: 'Artist',
            render: (row) => row.artist || <span className="text-secondary/50">—</span>,
        },
        {
            key: 'status',
            header: 'Status',
            width: '110px',
            render: (row) => (
                <span className={`px-2 py-0.5 rounded text-xs font-medium capitalize ${STATUS_STYLES[row.status]}`}>
                    {row.status}
                </span>
            ),
        },
        {
            key: 'hasFileId',
            header: 'File ID',
            width: '90px',
            render: (row) => row.hasFileId
                ? <FiCheckCircle className="text-success" size={16} />
                : <FiXCircle className="text-secondary/40" size={16} />,
        },
    ];

    return (
        <div>
            <button onClick={() => navigate('/bots')} className="flex items-center gap-1.5 text-sm text-secondary hover:text-dark mb-4 transition-colors">
                <FiArrowLeft size={14} /> Back to bots
            </button>

            <div className="flex items-center justify-between mb-6">
                <div>
                    <h1 className="text-xl font-bold text-dark">Samples</h1>
                    {bot && (
                        <p className="text-sm text-secondary mt-0.5">
                            for <Link to={`/bots/${botId}/edit`} className="text-primary hover:text-primary-dark font-medium">@{bot.username}</Link>
                        </p>
                    )}
                </div>
            </div>

            {/* Upload */}
            <div className="bg-white rounded-xl border border-border p-5 mb-6">
                <div className="mb-3 max-w-xs">
                    <label className="block text-xs font-medium text-secondary mb-1.5">Artist for this batch (optional)</label>
                    <input
                        value={batchArtist}
                        onChange={(e) => setBatchArtist(e.target.value)}
                        placeholder="Applied to every file you drop"
                        className="w-full px-3.5 py-2.5 border border-border rounded-lg text-sm focus:outline-none"
                    />
                </div>
                <div
                    {...getRootProps()}
                    className={`border-2 border-dashed rounded-lg p-6 text-center transition-colors ${
                        isUploading ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'
                    } ${isDragActive ? 'border-primary bg-primary-50' : 'border-border hover:border-primary/50'}`}
                >
                    <input {...getInputProps()} />
                    {isUploading ? (
                        <div className="flex items-center justify-center gap-2 text-sm text-secondary">
                            <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-primary" />
                            Uploading...
                        </div>
                    ) : (
                        <div>
                            <FiUploadCloud className="mx-auto text-secondary/40 mb-2" size={28} />
                            <p className="text-sm text-secondary">Drop audio files here or click to browse</p>
                            <p className="text-xs text-secondary/50 mt-1">MP3, WAV, M4A, OGG — multiple files supported</p>
                        </div>
                    )}
                </div>
            </div>

            <DataGrid<Sample>
                storageKey="Samples"
                queryKey={gridKey}
                searchPlaceholder="Search samples..."
                queryFn={async (params) => {
                    const res = await sampleApi.list(botId, {
                        page: params.page,
                        per_page: params.per_page,
                        search: params.search,
                    });
                    return res.data;
                }}
                columns={columns}
                actions={(row) => (
                    <>
                        {(row.status === 'failed' || row.status === 'pending') && (
                            <button
                                onClick={() => retryMutation.mutate(row.id)}
                                disabled={retryMutation.isPending}
                                title="Retry"
                                className="text-info hover:text-info/80 disabled:opacity-40"
                            >
                                <FiRefreshCw size={15} />
                            </button>
                        )}
                        <button
                            onClick={() => { if (confirm(`Delete sample "${row.title || row.id}"?`)) deleteMutation.mutate(row.id); }}
                            title="Delete"
                            className="text-error hover:text-error/80"
                        >
                            <FiTrash2 size={16} />
                        </button>
                    </>
                )}
            />
        </div>
    );
};

export default SampleListPage;
