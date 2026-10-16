// Remembers which tarombo tree an edit was opened from, so the edit page can
// link back to it. Kept in sessionStorage because saving redirects back to the
// edit page without any query string.
const STORAGE_KEY = 'tarombo:edit-return';
const TREE_PATH_PREFIX = '/dashboard/tarombo/full';

type TreeReturn = { personId: string; url: string };

export function rememberTreeReturn(personId: string): void {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        if (!window.location.pathname.startsWith(TREE_PATH_PREFIX)) {
            window.sessionStorage.removeItem(STORAGE_KEY);

            return;
        }

        const params = new URLSearchParams(window.location.search);
        params.set('person', personId);

        const entry: TreeReturn = {
            personId,
            url: `${window.location.pathname}?${params.toString()}`,
        };

        window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify(entry));
    } catch {
        // Storage can be unavailable (private mode, blocked site data).
    }
}

export function treeReturnFor(personId: number | string): string | null {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const raw = window.sessionStorage.getItem(STORAGE_KEY);

        if (!raw) {
            return null;
        }

        const entry = JSON.parse(raw) as Partial<TreeReturn>;

        return entry.personId === String(personId) &&
            typeof entry.url === 'string' &&
            entry.url.startsWith(TREE_PATH_PREFIX)
            ? entry.url
            : null;
    } catch {
        return null;
    }
}

const FEMALE_LINEAGE_KEY = 'tarombo:female-lineage';

/**
 * Remember the "Silsilah Perempuan Ditampilkan" choice for this tab, so the
 * tree looks the same (with its blue lineage line) when the user comes back
 * from an edit page.
 */
export function rememberFemaleLineage(shown: boolean): void {
    try {
        window.sessionStorage.setItem(FEMALE_LINEAGE_KEY, shown ? '1' : '0');
    } catch {
        // Ignore unavailable storage.
    }
}

export function rememberedFemaleLineage(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    try {
        return window.sessionStorage.getItem(FEMALE_LINEAGE_KEY) === '1';
    } catch {
        return false;
    }
}

export function clearTreeReturn(): void {
    try {
        window.sessionStorage.removeItem(STORAGE_KEY);
    } catch {
        // Ignore unavailable storage.
    }
}
