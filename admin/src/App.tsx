import { Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from './contexts/AuthContext';
import ProtectedRoute from './layout/ProtectedRoute';
import DashboardLayout from './layout/DashboardLayout';
import LoginPage from './modules/auth/LoginPage';
import BotListPage from './modules/bots/BotListPage';
import BotFormPage from './modules/bots/BotFormPage';
import SampleListPage from './modules/samples/SampleListPage';

const App = () => {
    return (
        <AuthProvider>
            <Routes>
                <Route path="/login" element={<LoginPage />} />

                <Route element={<ProtectedRoute />}>
                    <Route element={<DashboardLayout />}>
                        {/* Bots */}
                        <Route path="/bots" element={<BotListPage />} />
                        <Route path="/bots/new" element={<BotFormPage />} />
                        <Route path="/bots/:id/edit" element={<BotFormPage />} />

                        {/* Samples */}
                        <Route path="/bots/:id/samples" element={<SampleListPage />} />
                    </Route>
                </Route>

                <Route path="/" element={<Navigate to="/bots" replace />} />
                <Route path="*" element={<Navigate to="/login" replace />} />
            </Routes>
        </AuthProvider>
    );
};

export default App;
