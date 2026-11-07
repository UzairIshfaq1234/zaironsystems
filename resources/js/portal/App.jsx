import { Navigate, Outlet, Route, Routes, useLocation } from 'react-router-dom';
import { useAuth } from './auth';
import Shell from './Shell';
import { ForgotPassword, Login, ResetPassword } from './pages/SignIn';
import { Attendance, Home, Leave, Payslips, Profile, Reviews } from './pages/Screens';

function Splash() {
    return (
        <div className="a-splash" role="status">
            <span className="a-spinner lg" />
            <span className="sr-only">Loading…</span>
        </div>
    );
}

/** Signed-in area: remembers where the employee was heading. */
function RequireSignIn() {
    const { employee } = useAuth();
    const location = useLocation();
    if (employee === undefined) return <Splash />;
    if (!employee) return <Navigate to="/login" replace state={{ from: location.pathname }} />;
    return <Shell />;
}

function GuestOnly() {
    const { employee } = useAuth();
    if (employee === undefined) return <Splash />;
    return employee ? <Navigate to="/" replace /> : <Outlet />;
}

export default function App() {
    return (
        <Routes>
            <Route element={<GuestOnly />}>
                <Route path="login" element={<Login />} />
                <Route path="forgot-password" element={<ForgotPassword />} />
                <Route path="reset-password" element={<ResetPassword />} />
            </Route>
            <Route element={<RequireSignIn />}>
                <Route index element={<Home />} />
                <Route path="attendance" element={<Attendance />} />
                <Route path="leave" element={<Leave />} />
                <Route path="payslips" element={<Payslips />} />
                <Route path="reviews" element={<Reviews />} />
                <Route path="profile" element={<Profile />} />
                <Route path="*" element={<Navigate to="/" replace />} />
            </Route>
        </Routes>
    );
}
