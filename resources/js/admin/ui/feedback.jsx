import { createContext, useCallback, useContext, useRef, useState } from 'react';
import Icon from './Icon';
import { Button, Modal } from './index';

/* ---------- Toasts ---------- */

const ToastContext = createContext(() => {});

export function ToastProvider({ children }) {
    const [toasts, setToasts] = useState([]);
    const nextId = useRef(0);

    const toast = useCallback((message, tone = 'success') => {
        const id = ++nextId.current;
        setToasts((t) => [...t, { id, message, tone }]);
        setTimeout(() => setToasts((t) => t.filter((x) => x.id !== id)), 4200);
    }, []);

    return (
        <ToastContext.Provider value={toast}>
            {children}
            <div className="a-toasts" role="status" aria-live="polite">
                {toasts.map((t) => (
                    <div key={t.id} className={`a-toast tone-${t.tone}`}>
                        <Icon name={t.tone === 'error' ? 'close' : 'check'} />
                        <span>{t.message}</span>
                    </div>
                ))}
            </div>
        </ToastContext.Provider>
    );
}

/** toast(message, 'success' | 'error') */
export const useToast = () => useContext(ToastContext);

/* ---------- Confirm dialog ---------- */

const ConfirmContext = createContext(async () => false);

export function ConfirmProvider({ children }) {
    const [state, setState] = useState(null);

    const confirm = useCallback(
        (options) =>
            new Promise((resolve) => {
                setState({ ...options, resolve });
            }),
        [],
    );

    const close = (result) => {
        state?.resolve(result);
        setState(null);
    };

    return (
        <ConfirmContext.Provider value={confirm}>
            {children}
            <Modal
                open={!!state}
                onClose={() => close(false)}
                title={state?.title}
                size="sm"
                footer={
                    <>
                        <Button variant="secondary" onClick={() => close(false)}>
                            Cancel
                        </Button>
                        <Button variant={state?.danger ? 'danger' : 'primary'} onClick={() => close(true)} autoFocus>
                            {state?.confirmLabel ?? 'Confirm'}
                        </Button>
                    </>
                }
            >
                <p className="a-muted">{state?.message}</p>
            </Modal>
        </ConfirmContext.Provider>
    );
}

/** `if (await confirm({ title, message, confirmLabel, danger: true })) …` */
export const useConfirm = () => useContext(ConfirmContext);
