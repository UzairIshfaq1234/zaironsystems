import { useState } from 'react';
import { alertMessage, fieldErrors, post } from '@/lib/api';

/** Submission state for a form posting to `url`: idle → sending → sent, with server errors mapped back. */
export default function useSubmit(url) {
    const [state, setState] = useState({ status: 'idle', errors: {}, alert: null });

    const submit = async (payload) => {
        setState({ status: 'sending', errors: {}, alert: null });
        try {
            const data = await post(url, payload);
            setState({ status: 'sent', errors: {}, alert: null, message: data.message });
            return true;
        } catch (error) {
            setState({ status: 'idle', errors: fieldErrors(error), alert: alertMessage(error) });
            return false;
        }
    };

    const setErrors = (errors) => setState((s) => ({ ...s, errors }));

    return { ...state, sending: state.status === 'sending', sent: state.status === 'sent', submit, setErrors };
}

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
export const isEmail = (value) => EMAIL.test(value.trim());
