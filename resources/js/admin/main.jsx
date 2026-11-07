import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import App from './App';
import { AuthProvider } from './auth/AuthContext';
import { ConfirmProvider, ToastProvider } from './ui/feedback';

createRoot(document.getElementById('admin-root')).render(
    <React.StrictMode>
        <BrowserRouter basename="/admin">
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
