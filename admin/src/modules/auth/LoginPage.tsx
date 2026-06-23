import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';
import { toast } from 'react-toastify';

const LoginPage = () => {
    const navigate = useNavigate();
    const { login } = useAuth();
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [isLoading, setIsLoading] = useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsLoading(true);
        try {
            await login(email, password);
            navigate('/bots');
        } catch (err) {
            const message =
                (err as { response?: { data?: { message?: string } } }).response?.data?.message;
            toast.error(message || 'Invalid credentials');
        }
        setIsLoading(false);
    };

    return (
        <div className="min-h-screen flex items-center justify-center bg-dark">
            <div className="w-full max-w-sm">
                <div className="bg-white rounded-2xl shadow-xl p-8">
                    {/* Logo */}
                    <div className="flex items-center gap-3 mb-8">
                        <div className="w-10 h-10 rounded-xl bg-primary flex items-center justify-center text-white font-bold text-lg">V</div>
                        <div>
                            <div className="text-lg font-bold text-dark">VoiceBot</div>
                            <div className="text-[10px] text-secondary uppercase tracking-widest font-semibold">Admin Panel</div>
                        </div>
                    </div>

                    <form onSubmit={handleSubmit}>
                        <div className="mb-4">
                            <label className="block text-xs font-semibold text-dark-light mb-1.5 uppercase tracking-wide">Email</label>
                            <input
                                type="email"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                className="w-full px-3.5 py-2.5 border border-border rounded-lg text-sm focus:outline-none"
                                placeholder="admin@example.com"
                                required
                            />
                        </div>

                        <div className="mb-6">
                            <label className="block text-xs font-semibold text-dark-light mb-1.5 uppercase tracking-wide">Password</label>
                            <input
                                type="password"
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                className="w-full px-3.5 py-2.5 border border-border rounded-lg text-sm focus:outline-none"
                                required
                            />
                        </div>

                        <button
                            type="submit"
                            disabled={isLoading}
                            className="w-full bg-primary hover:bg-primary-dark text-white py-2.5 rounded-lg text-sm font-semibold transition-colors disabled:opacity-50"
                        >
                            {isLoading ? 'Signing in...' : 'Sign in'}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    );
};

export default LoginPage;
