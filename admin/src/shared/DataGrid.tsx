import { useState, useEffect, type ReactNode } from 'react';
import { FiSearch, FiX, FiChevronUp, FiChevronDown } from 'react-icons/fi';
import { useQuery } from '@tanstack/react-query';

export interface Column<T> {
    key: string;
    header: string;
    sortable?: boolean;
    width?: string;
    render?: (row: T) => ReactNode;
}

interface PaginatedResult<T> {
    data: T[];
    meta: {
        page: number;
        per_page: number;
        total: number;
        total_pages: number;
    };
}

interface DataGridProps<T> {
    storageKey: string;
    queryFn: (params: {
        page: number;
        per_page: number;
        search: string;
        sort: string;
        order: string;
    }) => Promise<PaginatedResult<T>>;
    columns: Column<T>[];
    queryKey: unknown[];
    actions?: (row: T) => ReactNode;
    searchPlaceholder?: string;
    searchable?: boolean;
}

const PER_PAGE_OPTIONS = [10, 20, 50, 100];

function DataGrid<T extends { id?: number | string }>({
    storageKey,
    queryFn,
    columns,
    queryKey,
    actions,
    searchPlaceholder = 'Search...',
    searchable = true,
}: DataGridProps<T>) {
    const [page, setPage] = useState(1);
    const [perPage, setPerPage] = useState(
        () => parseInt(localStorage.getItem(`${storageKey}_perPage`) || '20', 10),
    );
    const [sort, setSort] = useState('id');
    const [order, setOrder] = useState('desc');
    const [searchInput, setSearchInput] = useState('');
    const [search, setSearch] = useState('');

    useEffect(() => {
        const timer = setTimeout(() => {
            setSearch(searchInput);
            setPage(1);
        }, 300);
        return () => clearTimeout(timer);
    }, [searchInput]);

    useEffect(() => {
        localStorage.setItem(`${storageKey}_perPage`, String(perPage));
    }, [perPage, storageKey]);

    const changePerPage = (next: number) => {
        setPerPage(next);
        setPage(1);
    };

    const { data, isLoading } = useQuery({
        queryKey: [...queryKey, { page, per_page: perPage, search, sort, order }],
        queryFn: () => queryFn({ page, per_page: perPage, search, sort, order }),
    });

    const items = data?.data || [];
    const meta = data?.meta || { page: 1, per_page: 20, total: 0, total_pages: 1 };
    const totalPages = meta.total_pages;

    const handleSort = (key: string) => {
        if (sort === key) {
            setOrder(order === 'asc' ? 'desc' : 'asc');
        } else {
            setSort(key);
            setOrder('asc');
        }
        setPage(1);
    };

    const getPageNumbers = (): number[] => {
        const pages: number[] = [];
        let start = Math.max(1, page - 2);
        const end = Math.min(totalPages, start + 4);
        start = Math.max(1, end - 4);
        for (let i = start; i <= end; i++) pages.push(i);
        return pages;
    };

    const btnBase = 'px-3 py-1.5 text-xs font-medium border rounded-md transition-colors';
    const btnNormal = `${btnBase} border-border text-secondary hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed`;
    const btnActive = `${btnBase} border-primary bg-primary text-white`;

    return (
        <div>
            {/* Search */}
            {searchable && (
                <div className="mb-4">
                    <div className="relative max-w-sm">
                        <FiSearch className="absolute left-3 top-2.5 text-secondary/60" size={15} />
                        <input
                            type="text"
                            value={searchInput}
                            onChange={(e) => setSearchInput(e.target.value)}
                            placeholder={searchPlaceholder}
                            className="w-full pl-9 pr-8 py-2 border border-border rounded-lg text-sm bg-white focus:outline-none"
                        />
                        {searchInput && (
                            <button onClick={() => setSearchInput('')} className="absolute right-2.5 top-2.5 text-secondary/40 hover:text-secondary">
                                <FiX size={15} />
                            </button>
                        )}
                    </div>
                </div>
            )}

            {/* Table */}
            <div className="bg-white rounded-xl border border-border overflow-hidden">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-border">
                            {columns.map((col) => (
                                <th
                                    key={col.key}
                                    className={`px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-secondary/70 ${
                                        col.sortable ? 'cursor-pointer select-none hover:text-dark' : ''
                                    }`}
                                    style={col.width ? { width: col.width } : undefined}
                                    onClick={() => col.sortable && handleSort(col.key)}
                                >
                                    <span className="flex items-center gap-1">
                                        {col.header}
                                        {col.sortable && sort === col.key && (
                                            order === 'asc'
                                                ? <FiChevronUp size={13} className="text-primary" />
                                                : <FiChevronDown size={13} className="text-primary" />
                                        )}
                                    </span>
                                </th>
                            ))}
                            {actions && <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-secondary/70 w-32">Actions</th>}
                        </tr>
                    </thead>
                    <tbody>
                        {isLoading ? (
                            Array.from({ length: Math.min(perPage, 5) }).map((_, i) => (
                                <tr key={`skel-${i}`} className="border-b border-border/50">
                                    {columns.map((_, j) => (
                                        <td key={j} className="px-4 py-3.5">
                                            <div className="h-3.5 bg-gray-100 rounded-md animate-pulse" style={{ width: `${50 + (j * 13) % 40}%` }} />
                                        </td>
                                    ))}
                                    {actions && <td className="px-4 py-3.5"><div className="h-3.5 bg-gray-100 rounded-md animate-pulse w-14 ml-auto" /></td>}
                                </tr>
                            ))
                        ) : !items.length ? (
                            <tr>
                                <td colSpan={columns.length + (actions ? 1 : 0)} className="px-4 py-16 text-center text-secondary/60 text-sm">
                                    No data found
                                </td>
                            </tr>
                        ) : (
                            items.map((row, ri) => (
                                <tr key={row.id ?? ri} className="border-b border-border/50 last:border-0 hover:bg-primary-50/30 transition-colors">
                                    {columns.map((col) => (
                                        <td key={col.key} className="px-4 py-3">
                                            {col.render ? col.render(row) : ((row as Record<string, unknown>)[col.key] as ReactNode)}
                                        </td>
                                    ))}
                                    {actions && (
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex items-center justify-end gap-2">{actions(row)}</div>
                                        </td>
                                    )}
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            {/* Pagination */}
            {totalPages > 0 && (
                <div className="flex items-center justify-between mt-4">
                    <div className="flex items-center gap-1">
                        <button onClick={() => setPage(1)} disabled={page <= 1} className={btnNormal}>&laquo;</button>
                        <button onClick={() => setPage(page - 1)} disabled={page <= 1} className={btnNormal}>Previous</button>
                        {getPageNumbers().map((p) => (
                            <button key={p} onClick={() => setPage(p)} className={p === page ? btnActive : btnNormal}>
                                {p}
                            </button>
                        ))}
                        <button onClick={() => setPage(page + 1)} disabled={page >= totalPages} className={btnNormal}>Next</button>
                        <button onClick={() => setPage(totalPages)} disabled={page >= totalPages} className={btnNormal}>&raquo;</button>
                    </div>

                    <span className="text-xs text-secondary">
                        Page {meta.page} of {totalPages}
                    </span>

                    <select
                        value={perPage}
                        onChange={(e) => changePerPage(Number(e.target.value))}
                        className="px-3 py-1.5 text-xs border border-border rounded-md bg-white focus:outline-none"
                    >
                        {PER_PAGE_OPTIONS.map((n) => (
                            <option key={n} value={n}>{n} rows/page</option>
                        ))}
                    </select>
                </div>
            )}
        </div>
    );
}

export default DataGrid;
