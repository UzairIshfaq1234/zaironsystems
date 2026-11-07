// Employee portal API client (see resources/js/lib/createApi.js). Signed in with the employee guard.
import { ApiError, createApi } from '../../lib/createApi';

export { ApiError };

const portal = createApi('/portal/api', 'portal:unauthorized');

export const { api, setCsrf, fileUrl } = portal;
