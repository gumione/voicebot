import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'react-toastify';
import { FiArrowLeft, FiCopy, FiCheck } from 'react-icons/fi';
import botApi from '../../api/botApi';
import { FormField, FormInput } from '../../shared/FormField';
import type { BotPayload } from '../../types/bot';

const errMessage = (err: unknown) =>
    (err as { response?: { data?: { message?: string } } }).response?.data?.message;

const BotFormPage = () => {
    const { id } = useParams();
    const isEdit = Boolean(id);
    const navigate = useNavigate();
    const queryClient = useQueryClient();

    const [name, setName] = useState('');
    const [username, setUsername] = useState('');
    const [token, setToken] = useState('');
    const [storageChatId, setStorageChatId] = useState('');
    const [isActive, setIsActive] = useState(true);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [copied, setCopied] = useState(false);

    const { data: existing } = useQuery({
        queryKey: ['bot', id],
        queryFn: () => botApi.getById(Number(id)).then((r) => r.data.data),
        enabled: isEdit,
        staleTime: 0,
    });

    useEffect(() => {
        if (existing) {
            // Hydrate the form once the existing record loads.
            // eslint-disable-next-line react-hooks/set-state-in-effect
            setName(existing.name);
            setUsername(existing.username);
            setStorageChatId(existing.storageChatId || '');
            setIsActive(existing.isActive);
        }
    }, [existing]);

    const mutation = useMutation({
        mutationFn: () => {
            if (isEdit) {
                // Empty token means "keep existing" — backend honours an empty string.
                const payload: Partial<BotPayload> = {
                    name,
                    username,
                    token,
                    storageChatId,
                    isActive,
                };
                return botApi.update(Number(id), payload);
            }
            const payload: BotPayload = { name, username, token, storageChatId, isActive };
            return botApi.create(payload);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['bots-grid'] });
            toast.success(isEdit ? 'Bot updated' : 'Bot created');
            navigate('/bots');
        },
        onError: (err) => toast.error(errMessage(err) || 'Error'),
    });

    const validate = (): boolean => {
        const e: Record<string, string> = {};
        if (!name.trim()) e.name = 'Name is required';
        if (!username.trim()) e.username = 'Username is required';
        if (!isEdit && !token.trim()) e.token = 'Token is required';
        setErrors(e);
        return Object.keys(e).length === 0;
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (validate()) mutation.mutate();
    };

    const apiBase = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8123/admin/api';
    const backendOrigin = new URL(apiBase, window.location.origin).origin;
    const webhookUrl = existing
        ? `${backendOrigin}/bot/${existing.webhookToken}/webhook`
        : '';

    const copyWebhook = async () => {
        try {
            await navigator.clipboard.writeText(webhookUrl);
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        } catch {
            toast.error('Could not copy to clipboard');
        }
    };

    return (
        <div className="max-w-2xl">
            <button onClick={() => navigate('/bots')} className="flex items-center gap-1.5 text-sm text-secondary hover:text-dark mb-4 transition-colors">
                <FiArrowLeft size={14} /> Back to bots
            </button>

            <h1 className="text-xl font-bold text-dark mb-6">
                {isEdit ? 'Edit Bot' : 'New Bot'}
            </h1>

            <form onSubmit={handleSubmit} className="bg-white rounded-xl border border-border p-6 space-y-5">
                <FormField label="Name" required error={errors.name}>
                    <FormInput
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        error={errors.name}
                        placeholder="My Voice Bot"
                    />
                </FormField>

                <FormField label="Username" required error={errors.username}>
                    <FormInput
                        value={username}
                        onChange={(e) => setUsername(e.target.value)}
                        error={errors.username}
                        placeholder="my_voice_bot"
                    />
                </FormField>

                <FormField
                    label="Bot Token"
                    required={!isEdit}
                    error={errors.token}
                    hint={isEdit
                        ? (existing?.hasToken ? 'A token is currently set. Leave blank to keep it.' : 'No token is set yet.')
                        : 'From @BotFather.'}
                >
                    <FormInput
                        type="password"
                        value={token}
                        onChange={(e) => setToken(e.target.value)}
                        error={errors.token}
                        placeholder={isEdit ? 'Leave blank to keep existing' : '123456:ABC-DEF...'}
                        autoComplete="new-password"
                    />
                </FormField>

                <FormField label="Storage Chat ID" hint="Telegram chat used to store uploaded audio (optional).">
                    <FormInput
                        value={storageChatId}
                        onChange={(e) => setStorageChatId(e.target.value)}
                        placeholder="-1001234567890"
                    />
                </FormField>

                <label className="flex items-center gap-2.5 text-sm cursor-pointer">
                    <input
                        type="checkbox"
                        checked={isActive}
                        onChange={(e) => setIsActive(e.target.checked)}
                        className="w-4 h-4 rounded border-border text-primary focus:ring-primary/30"
                    />
                    <span className="text-dark-light font-medium">Active</span>
                </label>

                {/* Webhook info (edit only) */}
                {isEdit && existing && (
                    <div className="border-t border-border pt-5">
                        <label className="block text-xs font-medium text-secondary mb-1.5">Webhook URL</label>
                        <div className="flex items-center gap-2">
                            <code className="flex-1 px-3 py-2 bg-neutral border border-border rounded-lg text-xs text-dark-light break-all">
                                {webhookUrl}
                            </code>
                            <button
                                type="button"
                                onClick={copyWebhook}
                                title="Copy webhook URL"
                                className="shrink-0 flex items-center gap-1.5 border border-border hover:bg-gray-50 text-secondary px-3 py-2 rounded-lg text-xs font-medium transition-colors"
                            >
                                {copied ? <FiCheck size={14} className="text-success" /> : <FiCopy size={14} />}
                                {copied ? 'Copied' : 'Copy'}
                            </button>
                        </div>
                        <p className="text-secondary/70 text-xs mt-2">
                            Register it with Telegram via <span className="font-medium">setWebhook</span>, passing{' '}
                            <code className="px-1 py-0.5 bg-neutral rounded">secret_token={existing.webhookToken}</code>.
                            Telegram needs a <span className="font-medium">public HTTPS</span> host — swap in your tunnel/prod domain if this points at a local one.
                        </p>
                    </div>
                )}

                {/* Actions */}
                <div className="flex gap-3 pt-5 border-t border-border">
                    <button
                        type="submit"
                        disabled={mutation.isPending}
                        className="bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg text-sm font-semibold transition-colors disabled:opacity-50"
                    >
                        {mutation.isPending ? 'Saving...' : isEdit ? 'Update' : 'Create'}
                    </button>
                    <button
                        type="button"
                        onClick={() => navigate('/bots')}
                        className="border border-border hover:bg-gray-50 text-secondary px-6 py-2.5 rounded-lg text-sm font-medium transition-colors"
                    >
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    );
};

export default BotFormPage;
