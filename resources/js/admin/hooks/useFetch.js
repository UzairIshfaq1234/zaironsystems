import { api } from '../lib/api';
import { createUseFetch, useDebounced } from '../../lib/useFetch';

export { useDebounced };

export default createUseFetch(api);