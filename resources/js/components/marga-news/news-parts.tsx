import { Link } from '@inertiajs/react';

export type MargaNewsItem = {
    id: number;
    title: string;
    url: string;
    publisher: string | null;
    excerpt: string | null;
    summary: string | null;
    content: string | null;
    image_url: string | null;
    published_at: string | null;
    updated_at: string | null;
    margas: { id: number; name: string; color: string | null }[];
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

const dateFormatter = new Intl.DateTimeFormat('id-ID', { dateStyle: 'long' });

export function formatNewsDate(value: string | null): string | null {
    return value ? dateFormatter.format(new Date(value)) : null;
}

export function MargaChips({ margas }: { margas: MargaNewsItem['margas'] }) {
    if (margas.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-wrap gap-1.5">
            {margas.map((marga) => (
                <span
                    key={marga.id}
                    className="inline-flex items-center gap-1.5 rounded-full border border-tb-outline-variant bg-tb-surface-container px-2 py-0.5 text-[11px] font-semibold text-tb-on-surface"
                >
                    <span
                        aria-hidden
                        className="size-2 rounded-full"
                        style={{
                            backgroundColor:
                                marga.color ?? 'var(--color-tb-primary)',
                        }}
                    />
                    {marga.name}
                </span>
            ))}
        </div>
    );
}

export function Pager({ page }: { page: Paginated<unknown> }) {
    if (page.last_page <= 1) {
        return null;
    }

    const linkClass =
        'rounded-lg border border-tb-outline-variant px-3 py-1.5 text-sm font-medium text-tb-on-surface transition-colors hover:bg-tb-surface-container';

    return (
        <div className="flex items-center justify-between gap-3">
            {page.prev_page_url ? (
                <Link
                    href={page.prev_page_url}
                    preserveScroll
                    className={linkClass}
                >
                    ← Sebelumnya
                </Link>
            ) : (
                <span />
            )}
            <span className="text-xs text-tb-on-surface-variant">
                Halaman {page.current_page} dari {page.last_page}
            </span>
            {page.next_page_url ? (
                <Link
                    href={page.next_page_url}
                    preserveScroll
                    className={linkClass}
                >
                    Berikutnya →
                </Link>
            ) : (
                <span />
            )}
        </div>
    );
}
