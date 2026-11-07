// Admin API client (see lib/createApi.js): same-origin session auth with CSRF, JSON in/out.
import { ApiError, createApi } from '../../lib/createApi';

export { ApiError };

const admin = createApi('/admin/api', 'admin:unauthorized');

export const { api, setCsrf, fileUrl } = admin;
