import { useCallback, useSyncExternalStore } from 'react';

const mediaQueryLists = new Map<string, MediaQueryList>();

function mediaQueryList(query: string): MediaQueryList | undefined {
    if (typeof window === 'undefined') {
        return undefined;
    }

    let mql = mediaQueryLists.get(query);

    if (!mql) {
        mql = window.matchMedia(query);
        mediaQueryLists.set(query, mql);
    }

    return mql;
}

/**
 * Whether the CSS media query matches. `serverMatches` is used while
 * rendering on the server, where there is no viewport to measure.
 */
export function useMediaQuery(query: string, serverMatches = false): boolean {
    const subscribe = useCallback(
        (callback: () => void) => {
            const mql = mediaQueryList(query);

            if (!mql) {
                return () => {};
            }

            mql.addEventListener('change', callback);

            return () => {
                mql.removeEventListener('change', callback);
            };
        },
        [query],
    );

    return useSyncExternalStore(
        subscribe,
        () => mediaQueryList(query)?.matches ?? serverMatches,
        () => serverMatches,
    );
}
