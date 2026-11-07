import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import App from './App';
import { AuthProvider } from './auth';
import { ConfirmProvider, ToastProvider } from '../admin/ui/feedback';

createRoot(document.getElementById('portal-root')).render(
    <React.StrictMode>
        <BrowserRouter basename="/portal">
            <ToastProvider>
                <ConfirmProvider>
                    <AuthProvider>
                        <App />
                    </AuthProvider>
                </ConfirmProvider>
            </ToastProvider>
        </BrowserRouter>
    </React.StrictMode>,
);
