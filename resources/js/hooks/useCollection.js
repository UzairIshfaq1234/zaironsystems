import { useMemo, useState } from 'react';

const categoriesOf = (item) => item.cats ?? [item.cat];

/**
 * Filter + paginate a list. `visible` is the current page; `upTo` is every page so far (for "Load more").
 */
export default function useCollection(items, pageSize = Infinity) {
    const [filter, setFilterState] = useState('all');
    const [page, setPage] = useState(1);

    const filtered = useMemo(
        () => (filter === 'all' ? items : items.filter((item) => categoriesOf(item).includes(filter))),
        [items, filter],
    );

    const pageCount = Math.max(1, Math.ceil(filtered.length / pageSize));
    const current = Math.min(page, pageCount);

    const setFilter = (value) => {
        setFilterState(value);
        setPage(1);
    };

    return {
        filter,
        setFilter,
        page: current,
        setPage,
        pageCount,
        hasMore: current < pageCount,
        visible: filtered.slice((current - 1) * pageSize, current * pageSize),
        upTo: filtered.slice(0, current * pageSize),
    };
}
