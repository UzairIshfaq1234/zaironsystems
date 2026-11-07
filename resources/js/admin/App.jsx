import { Navigate, Outlet, Route, Routes, useLocation } from 'react-router-dom';
import { useAuth } from './auth/AuthContext';
import AdminLayout from './layout/AdminLayout';
import { ForgotPassword, Login, ResetPassword } from './pages/auth';
import { EnquiryDetail, EnquiryList } from './pages/Enquiries';
import { JobApplicationDetail, JobApplicationList, JobOpeningList } from './pages/Careers';
import { Activity, Forbidden, Home, NotFound, Profile } from './pages/misc';
import Overview from './pages/Overview';
import { RoleEditor, RoleList } from './pages/Roles';
import Subscribers from './pages/Subscribers';
import Users from './pages/Users';
import { ClientDetail, ClientList } from './pages/billing/Clients';
import { DocumentEditor, DocumentList, DocumentView } from './pages/billing/Documents';
import BillingSettings from './pages/billing/Settings';
import Employees from './pages/hr/Employees';
import EmployeeDetail from './pages/hr/EmployeeDetail';
import Attendance from './pages/hr/Attendance';
import Leave from './pages/hr/Leave';
import Reviews from './pages/hr/Reviews';
import Payroll from './pages/hr/Payroll';
import { AllowancesPage, DepartmentsPage, JobTitlesPage } from './pages/hr/Lists';
import PayrollRun from './pages/hr/PayrollRun';
import { AccountLedger, AccountList } from './pages/accounting/Accounts';
import Expenses from './pages/accounting/Expenses';
import Categories from './pages/accounting/Categories';
import Journal from './pages/accounting/Journal';
import AccountingOverview from './pages/accounting/Overview';
import Reports from './pages/accounting/Reports';
import AccountingSettings from './pages/accounting/Settings';
import Vendors from './pages/accounting/Vendors';

function Splash() {
    return (
        <div className="a-splash" role="status">
            <span className="a-spinner lg" />
            <span className="sr-only">Loading…</span>
        </div>
    );
}

/** Signed-in area; remembers where the visitor was heading. */
function RequireAuth() {
    const { user } = useAuth();
    const location = useLocation();
    if (user === undefined) return <Splash />;
    if (!user) return <Navigate to="/login" replace state={{ from: location.pathname + location.search }} />;
    return <AdminLayout />;
}

function GuestOnly() {
    const { user } = useAuth();
    if (user === undefined) return <Splash />;
    return user ? <Navigate to="/" replace /> : <Outlet />;
}

/** Cosmetic gate (the API enforces the same permission). */
function Can({ permission, children }) {
    const { can } = useAuth();
    return can(permission) ? children : <Forbidden />;
}

export default function App() {
    return (
        <Routes>
            <Route element={<GuestOnly />}>
                <Route path="login" element={<Login />} />
                <Route path="forgot-password" element={<ForgotPassword />} />
            </Route>
            <Route path="reset-password" element={<ResetPassword />} />

            <Route element={<RequireAuth />}>
                <Route
                    index
                    element={
                        <Home>
                            <Overview />
                        </Home>
                    }
                />
                <Route path="enquiries" element={<Can permission="enquiries.view"><EnquiryList /></Can>} />
                <Route path="enquiries/:id" element={<Can permission="enquiries.view"><EnquiryDetail /></Can>} />
                <Route path="careers" element={<Navigate to="/careers/openings" replace />} />
                <Route path="careers/openings" element={<Can permission="careers.view"><JobOpeningList /></Can>} />
                <Route path="careers/applications" element={<Can permission="careers.view"><JobApplicationList /></Can>} />
                <Route path="careers/applications/:id" element={<Can permission="careers.view"><JobApplicationDetail /></Can>} />
                <Route path="subscribers" element={<Can permission="subscribers.view"><Subscribers /></Can>} />
                <Route path="users" element={<Can permission="users.view"><Users /></Can>} />
                <Route path="roles" element={<Can permission="roles.view"><RoleList /></Can>} />
                <Route path="roles/:id" element={<Can permission="roles.view"><RoleEditor /></Can>} />
                <Route path="activity" element={<Can permission="activity.view"><Activity /></Can>} />


                <Route path="billing/clients" element={<Can permission="billing.view"><ClientList /></Can>} />
                <Route path="billing/clients/:id" element={<Can permission="billing.view"><ClientDetail /></Can>} />
                <Route path="billing/settings" element={<Can permission="billing.view"><BillingSettings /></Can>} />
                {['quote', 'invoice'].map((kind) => [
                    <Route key={`${kind}-list`} path={`billing/${kind}s`} element={<Can permission="billing.view"><DocumentList kind={kind} key={kind} /></Can>} />,
                    <Route key={`${kind}-new`} path={`billing/${kind}s/new`} element={<Can permission="billing.manage"><DocumentEditor kind={kind} key={`${kind}-new`} /></Can>} />,
                    <Route key={`${kind}-view`} path={`billing/${kind}s/:id`} element={<Can permission="billing.view"><DocumentView kind={kind} key={kind} /></Can>} />,
                    <Route key={`${kind}-edit`} path={`billing/${kind}s/:id/edit`} element={<Can permission="billing.manage"><DocumentEditor kind={kind} key={`${kind}-edit`} /></Can>} />,
                ])}
                <Route path="accounting" element={<Can permission="accounting.view"><AccountingOverview /></Can>} />
                <Route path="accounting/expenses" element={<Can permission="accounting.view"><Expenses /></Can>} />
                <Route path="accounting/categories" element={<Can permission="accounting.view"><Categories /></Can>} />
                <Route path="accounting/vendors" element={<Can permission="accounting.view"><Vendors /></Can>} />
                <Route path="accounting/journal" element={<Can permission="accounting.view"><Journal /></Can>} />
                <Route path="accounting/accounts" element={<Can permission="accounting.view"><AccountList /></Can>} />
                <Route path="accounting/accounts/:id" element={<Can permission="accounting.view"><AccountLedger /></Can>} />
                <Route path="accounting/reports" element={<Can permission="accounting.view"><Reports /></Can>} />
                <Route path="accounting/settings" element={<Can permission="accounting.view"><AccountingSettings /></Can>} />
                <Route path="people" element={<Can permission="hr.view"><Employees /></Can>} />
                <Route path="people/departments" element={<Can permission="hr.manage"><DepartmentsPage /></Can>} />
                <Route path="people/job-titles" element={<Can permission="hr.manage"><JobTitlesPage /></Can>} />
                <Route path="people/allowances" element={<Can permission="hr.manage"><AllowancesPage /></Can>} />
                <Route path="people/:id" element={<Can permission="hr.view"><EmployeeDetail /></Can>} />
                <Route path="attendance" element={<Can permission="hr.view"><Attendance /></Can>} />
                <Route path="leave" element={<Can permission="hr.view"><Leave /></Can>} />
                <Route path="reviews" element={<Can permission="hr.view"><Reviews /></Can>} />
                <Route path="payroll" element={<Can permission="payroll.view"><Payroll /></Can>} />
                <Route path="payroll/:id" element={<Can permission="payroll.view"><PayrollRun /></Can>} />                <Route path="profile" element={<Profile />} />
                <Route path="*" element={<NotFound />} />
            </Route>
        </Routes>
    );
}
