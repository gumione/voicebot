import { Link } from 'react-router-dom';
import { FiPlus, FiEdit2, FiTrash2, FiMusic } from 'react-icons/fi';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'react-toastify';
import botApi from '../../api/botApi';
import DataGrid, { type Column } from '../../shared/DataGrid';
import type { Bot } from '../../types/bot';

const formatDate = (value: string) => {
    const d = new Date(value);
    return Number.isNaN(d.getTime()) ? '—' : d.toLocaleDateString();
};

const columns: Column<Bot>[] = [
    {
        key: 'username',
        header: 'Username',
        render: (row) => <span className="font-medium text-dark">@{row.username}</span>,
    },
    { key: 'name', header: 'Name' },
    {
        key: 'sampleCount',
        header: 'Samples',
        width: '100px',
        render: (row) => <span className="tabular-nums">{row.sampleCount}</span>,
    },
    {
        key: 'isActive',
        header: 'Status',
        width: '100px',
        render: (row) => (
            <span className={`px-2 py-0.5 rounded text-xs font-medium ${row.isActive ? 'bg-success/10 text-success' : 'bg-gray-100 text-secondary'}`}>
                {row.isActive ? 'Active' : 'Inactive'}
            </span>
        ),
    },
    {
        key: 'createdAt',
        header: 'Created',
        width: '130px',
        render: (row) => <span className="text-secondary">{formatDate(row.createdAt)}</span>,
    },
];

const BotListPage = () => {
    const queryClient = useQueryClient();

    const deleteMutation = useMutation({
        mutationFn: (id: number) => botApi.delete(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['bots-grid'] });
            toast.success('Bot deleted');
        },
        onError: (err) => {
            const message = (err as { response?: { data?: { message?: string } } }).response?.data?.message;
            toast.error(message || 'Error');
        },
    });

    return (
        <div>
            <div className="flex items-center justify-between mb-6">
                <h1 className="text-xl font-bold text-dark">Bots</h1>
                <Link to="/bots/new" className="flex items-center gap-2 bg-primary hover:bg-primary-dark text-white px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors">
                    <FiPlus size={15} /> Add bot
                </Link>
            </div>

            <DataGrid<Bot>
                storageKey="Bots"
                queryKey={['bots-grid']}
                searchable={false}
                queryFn={async (params) => {
                    const res = await botApi.list({ page: params.page, per_page: params.per_page });
                    return res.data;
                }}
                columns={columns}
                actions={(row) => (
                    <>
                        <Link
                            to={`/bots/${row.id}/samples`}
                            title="Samples"
                            className="flex items-center gap-1 text-xs font-medium text-primary hover:text-primary-dark"
                        >
                            <FiMusic size={15} /> Samples
                        </Link>
                        <Link to={`/bots/${row.id}/edit`} title="Edit" className="text-info hover:text-info/80">
                            <FiEdit2 size={16} />
                        </Link>
                        <button
                            onClick={() => { if (confirm(`Delete bot @${row.username}?`)) deleteMutation.mutate(row.id); }}
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

export default BotListPage;
