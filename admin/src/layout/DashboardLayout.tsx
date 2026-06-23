import { Outlet } from 'react-router-dom';
import Sidebar from './Sidebar';

const DashboardLayout = () => {
    return (
        <div className="flex min-h-screen bg-neutral">
            <Sidebar />
            <main className="flex-1 p-8 overflow-auto">
                <Outlet />
            </main>
        </div>
    );
};

export default DashboardLayout;
