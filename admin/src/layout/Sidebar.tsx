import { NavLink } from 'react-router-dom';
import { FiCpu, FiLogOut } from 'react-icons/fi';
import { useAuth } from '../contexts/AuthContext';

const menuItems = [
    { to: '/bots', icon: <FiCpu />, label: 'Bots' },
];

const Sidebar = () => {
    const { user, logout } = useAuth();

    return (
        <aside className="w-60 bg-dark text-white flex flex-col min-h-screen shrink-0">
            {/* Logo */}
            <div className="px-5 py-5 border-b border-white/[0.06]">
                <div className="flex items-center gap-2.5">
                    <div className="w-8 h-8 rounded-lg bg-primary flex items-center justify-center text-white font-bold text-sm">V</div>
                    <div>
                        <div className="text-sm font-bold tracking-wide">VoiceBot</div>
                        <div className="text-[10px] text-white/40 font-medium uppercase tracking-widest">Admin</div>
                    </div>
                </div>
            </div>

            {/* Nav */}
            <nav className="flex-1 py-3 overflow-y-auto">
                {menuItems.map((item) => (
                    <NavLink
                        key={item.to}
                        to={item.to}
                        className={({ isActive }) =>
                            `flex items-center gap-3 px-5 py-2 text-[13px] font-medium transition-all mx-2 rounded-lg mb-0.5 ${
                                isActive
                                    ? 'bg-primary/15 text-primary'
                                    : 'text-white/50 hover:text-white/80 hover:bg-white/[0.04]'
                            }`
                        }
                    >
                        <span className="text-base">{item.icon}</span>
                        {item.label}
                    </NavLink>
                ))}
            </nav>

            {/* User */}
            <div className="px-5 py-4 border-t border-white/[0.06]">
                <div className="flex items-center gap-3 mb-3">
                    <div className="w-8 h-8 rounded-full bg-dark-light flex items-center justify-center text-xs font-semibold text-white/60">
                        {(user?.email || 'U')[0].toUpperCase()}
                    </div>
                    <div className="text-xs truncate">
                        <div className="text-white/80 font-medium">Admin</div>
                        <div className="text-white/30 truncate">{user?.email}</div>
                    </div>
                </div>
                <button
                    onClick={logout}
                    className="flex items-center gap-2 text-xs text-white/30 hover:text-white/60 transition-colors"
                >
                    <FiLogOut size={13} />
                    Sign out
                </button>
            </div>
        </aside>
    );
};

export default Sidebar;
