import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ImagePlus, Newspaper, Send, X } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import { AppAvatar } from '@/components/app-avatar';
import InputError from '@/components/input-error';
import { FeedCard } from '@/components/news-feed/feed-card';
import type { FeedItem } from '@/components/news-feed/feed-card';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import { login } from '@/routes';
import newsFeed from '@/routes/news-feed';

type FeedCursor = Record<string, string | null>;

type NewsFeedProps = {
    items: FeedItem[];
    cursor: FeedCursor | null;
    hasMore: boolean;
    margas: { id: number; name: string }[];
};

const MAX_IMAGES = 4;

export default function NewsFeed({
    items: initialItems,
    cursor: initialCursor,
    hasMore: initialHasMore,
    margas,
}: NewsFeedProps) {
    const { auth } = usePage().props;
    const [audience, setAudience] = useState<'public' | 'marga'>('public');
    const [selectedMargas, setSelectedMargas] = useState<number[]>([]);
    const [margaSearch, setMargaSearch] = useState('');
    const [images, setImages] = useState<{ file: File; url: string }[]>([]);
    const fileInputRef = useRef<HTMLInputElement>(null);

    // Older pages are kept separately so a fresh server render (new post, like,
    // delete) refreshes the top of the feed without discarding what was loaded.
    const [appended, setAppended] = useState<FeedItem[]>([]);
    const [cursor, setCursor] = useState(initialCursor);
    const [hasMore, setHasMore] = useState(initialHasMore);
    const [loadingMore, setLoadingMore] = useState(false);

    const items = useMemo(() => {
        const seen = new Set<string>();

        return [...initialItems, ...appended].filter((item) => {
            if (seen.has(item.key)) {
                return false;
            }

            seen.add(item.key);

            return true;
        });
    }, [initialItems, appended]);

    const form = useForm<{
        body: string;
        audience: 'public' | 'marga';
        marga_ids: number[];
        images: File[];
    }>({
        body: '',
        audience: 'public',
        marga_ids: [],
        images: [],
    });

    const addImages = (incoming: File[]) => {
        const pictures = incoming.filter((file) =>
            file.type.startsWith('image/'),
        );

        if (pictures.length === 0) {
            return;
        }

        setImages((current) => {
            const room = MAX_IMAGES - current.length;

            if (pictures.length > room) {
                toast.error(`Maksimal ${MAX_IMAGES} gambar.`);
            }

            return [
                ...current,
                ...pictures.slice(0, room).map((file) => ({
                    file,
                    url: URL.createObjectURL(file),
                })),
            ];
        });
    };

    const removeImage = (index: number) =>
        setImages((current) => {
            URL.revokeObjectURL(current[index].url);

            return current.filter((_, position) => position !== index);
        });

    const audienceLabel =
        audience === 'public'
            ? 'Publik'
            : margas
                  .filter((marga) => selectedMargas.includes(marga.id))
                  .map((marga) => marga.name)
                  .join(', ') || 'Pilih marga';

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            audience,
            marga_ids: audience === 'marga' ? selectedMargas : [],
            images: images.map((image) => image.file),
        }));

        form.post(newsFeed.posts.store().url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setAudience('public');
                setSelectedMargas([]);
                setMargaSearch('');
                setImages((current) => {
                    current.forEach((image) => URL.revokeObjectURL(image.url));

                    return [];
                });

                if (fileInputRef.current) {
                    fileInputRef.current.value = '';
                }
            },
        });
    };

    const loadMore = async () => {
        if (!cursor || loadingMore) {
            return;
        }

        setLoadingMore(true);

        try {
            const query = new URLSearchParams();

            for (const [source, value] of Object.entries(cursor)) {
                if (value !== null) {
                    query.set(`cursor[${source}]`, value);
                }
            }

            const response = await fetch(
                `${newsFeed.index().url}?${query.toString()}`,
                { headers: { Accept: 'application/json' } },
            );

            if (!response.ok) {
                throw new Error('Gagal memuat.');
            }

            const payload = (await response.json()) as {
                items: FeedItem[];
                cursor: FeedCursor | null;
                has_more: boolean;
            };

            setAppended((current) => [...current, ...payload.items]);
            setCursor(payload.cursor);
            setHasMore(payload.has_more);
        } catch {
            toast.error('Gagal memuat kabar lama.');
        } finally {
            setLoadingMore(false);
        }
    };

    const imageError =
        form.errors.images ??
        Object.entries(form.errors).find(([key]) =>
            key.startsWith('images.'),
        )?.[1];

    return (
        <>
            <Head title="News Feed" />

            <div className="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-5 p-4 md:p-8">
                <div className="border-b border-tb-outline-variant pb-4">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <Newspaper className="size-6 text-tb-primary" />
                            <h1 className="font-display text-2xl font-bold text-tb-on-surface md:text-3xl">
                                News Feed
                            </h1>
                        </div>
                        <span className="text-xs text-tb-on-surface-variant">
                            Kabar keluarga
                        </span>
                    </div>
                </div>

                {auth.user ? (
                    <Card className="overflow-hidden rounded-xl border-tb-outline-variant bg-tb-surface-bright shadow-sm">
                        <CardContent className="p-4">
                            <form
                                onSubmit={submit}
                                encType="multipart/form-data"
                                className="grid gap-3"
                            >
                                <div className="flex items-start gap-3">
                                    <AppAvatar
                                        name={auth.user.name}
                                        className="mt-0.5"
                                    />
                                    <textarea
                                        value={form.data.body}
                                        onChange={(event) =>
                                            form.setData(
                                                'body',
                                                event.target.value,
                                            )
                                        }
                                        onPaste={(event) => {
                                            const pasted = Array.from(
                                                event.clipboardData.items,
                                            )
                                                .filter(
                                                    (entry) =>
                                                        entry.kind === 'file',
                                                )
                                                .map((entry) =>
                                                    entry.getAsFile(),
                                                )
                                                .filter(
                                                    (file): file is File =>
                                                        file !== null,
                                                );

                                            if (pasted.length > 0) {
                                                event.preventDefault();
                                                addImages(pasted);
                                            }
                                        }}
                                        onDragOver={(event) =>
                                            event.preventDefault()
                                        }
                                        onDrop={(event) => {
                                            event.preventDefault();
                                            addImages(
                                                Array.from(
                                                    event.dataTransfer.files,
                                                ),
                                            );
                                        }}
                                        rows={3}
                                        maxLength={2000}
                                        placeholder={`Apa yang ingin Anda bagikan, ${auth.user.name.split(' ')[0]}? Tempel gambar dengan Ctrl+V.`}
                                        className="min-h-20 flex-1 resize-none rounded-2xl border border-tb-outline-variant bg-tb-surface-container/40 px-4 py-3 text-sm text-tb-on-surface outline-none placeholder:text-tb-on-surface-variant focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20"
                                    />
                                </div>
                                <InputError
                                    message={form.errors.body}
                                    className="ml-11"
                                />

                                {images.length > 0 && (
                                    <div className="ml-11 grid grid-cols-4 gap-2">
                                        {images.map((image, index) => (
                                            <div
                                                key={image.url}
                                                className="relative"
                                            >
                                                <img
                                                    src={image.url}
                                                    alt={`Pratinjau ${index + 1}`}
                                                    className="aspect-square w-full rounded-lg border border-tb-outline-variant object-cover"
                                                />
                                                <button
                                                    type="button"
                                                    aria-label={`Hapus gambar ${index + 1}`}
                                                    onClick={() =>
                                                        removeImage(index)
                                                    }
                                                    className="absolute top-1 right-1 inline-flex size-6 items-center justify-center rounded-full bg-black/60 text-white transition-colors hover:bg-black/80"
                                                >
                                                    <X className="size-3.5" />
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                )}
                                <InputError
                                    message={imageError}
                                    className="ml-11"
                                />

                                <div className="flex flex-wrap items-start justify-end gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="mr-auto"
                                        disabled={images.length >= MAX_IMAGES}
                                        onClick={() =>
                                            fileInputRef.current?.click()
                                        }
                                    >
                                        <ImagePlus className="size-4" /> Gambar
                                    </Button>
                                    <input
                                        ref={fileInputRef}
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp,image/gif"
                                        multiple
                                        className="hidden"
                                        onChange={(event) => {
                                            addImages(
                                                Array.from(
                                                    event.target.files ?? [],
                                                ),
                                            );
                                            event.target.value = '';
                                        }}
                                    />
                                    <details className="w-full rounded-xl border border-tb-outline-variant bg-tb-surface-bright sm:w-64">
                                        <summary
                                            aria-label="Pilih audiens status"
                                            className="cursor-pointer px-3 py-2 text-sm text-tb-on-surface focus-visible:outline-2 focus-visible:outline-tb-primary"
                                        >
                                            {audienceLabel}
                                        </summary>
                                        <div className="grid gap-3 border-t border-tb-outline-variant p-3">
                                            <Label className="flex cursor-pointer items-center gap-2">
                                                <Checkbox
                                                    checked={
                                                        audience === 'public'
                                                    }
                                                    onCheckedChange={(
                                                        checked,
                                                    ) => {
                                                        setAudience(
                                                            checked === true
                                                                ? 'public'
                                                                : 'marga',
                                                        );
                                                        setSelectedMargas([]);
                                                    }}
                                                />
                                                Publik
                                            </Label>
                                            <p className="text-xs text-tb-on-surface-variant">
                                                Publik: terlihat oleh semua
                                                pengunjung, termasuk yang belum
                                                login. Atau centang marga
                                                tertentu di bawah.
                                            </p>
                                            <Input
                                                aria-label="Cari marga audiens"
                                                placeholder="Cari marga..."
                                                value={margaSearch}
                                                onChange={(event) =>
                                                    setMargaSearch(
                                                        event.target.value,
                                                    )
                                                }
                                                onKeyDown={(event) => {
                                                    if (event.key === 'Enter') {
                                                        event.preventDefault();
                                                    }
                                                }}
                                            />
                                            <div
                                                role="group"
                                                aria-label="Daftar marga audiens"
                                                className="grid max-h-48 gap-2 overflow-y-auto"
                                            >
                                                {margas
                                                    .filter((marga) =>
                                                        marga.name
                                                            .toLocaleLowerCase()
                                                            .includes(
                                                                margaSearch
                                                                    .trim()
                                                                    .toLocaleLowerCase(),
                                                            ),
                                                    )
                                                    .map((marga) => (
                                                        <Label
                                                            key={marga.id}
                                                            className="flex cursor-pointer items-center gap-2 py-1"
                                                        >
                                                            <Checkbox
                                                                checked={selectedMargas.includes(
                                                                    marga.id,
                                                                )}
                                                                onCheckedChange={(
                                                                    checked,
                                                                ) => {
                                                                    setAudience(
                                                                        'marga',
                                                                    );
                                                                    setSelectedMargas(
                                                                        checked ===
                                                                            true
                                                                            ? [
                                                                                  ...selectedMargas,
                                                                                  marga.id,
                                                                              ]
                                                                            : selectedMargas.filter(
                                                                                  (
                                                                                      id,
                                                                                  ) =>
                                                                                      id !==
                                                                                      marga.id,
                                                                              ),
                                                                    );
                                                                }}
                                                            />
                                                            {marga.name}
                                                        </Label>
                                                    ))}
                                                {!margas.some((marga) =>
                                                    marga.name
                                                        .toLocaleLowerCase()
                                                        .includes(
                                                            margaSearch
                                                                .trim()
                                                                .toLocaleLowerCase(),
                                                        ),
                                                ) && (
                                                    <p className="text-xs text-tb-on-surface-variant">
                                                        {margas.length === 0
                                                            ? 'Daftar Marga belum tersedia.'
                                                            : 'Marga tidak ditemukan.'}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    </details>
                                    <Button
                                        type="submit"
                                        disabled={
                                            form.processing ||
                                            (form.data.body.trim() === '' &&
                                                images.length === 0)
                                        }
                                        className="rounded-full px-5"
                                    >
                                        <Send className="size-4" />
                                        {form.processing
                                            ? 'Membagikan...'
                                            : 'Bagikan Status'}
                                    </Button>
                                </div>
                                <InputError
                                    message={
                                        form.errors.audience ??
                                        Object.entries(form.errors).find(
                                            ([key]) =>
                                                key === 'marga_ids' ||
                                                key.startsWith('marga_ids.'),
                                        )?.[1]
                                    }
                                />
                            </form>
                        </CardContent>
                    </Card>
                ) : (
                    <Card className="overflow-hidden rounded-xl border-tb-outline-variant bg-tb-surface-bright shadow-sm">
                        <CardContent className="p-4">
                            <Link
                                href={login()}
                                className="flex items-center gap-3"
                            >
                                <AppAvatar name="Anda" className="shrink-0" />
                                <span className="flex h-11 flex-1 items-center rounded-2xl border border-tb-outline-variant bg-tb-surface-container/40 px-4 text-sm text-tb-on-surface-variant transition-colors hover:bg-tb-surface-container">
                                    Bagikan cerita Anda...
                                </span>
                                <span className="hidden rounded-full bg-tb-primary px-4 py-2 text-xs font-semibold text-white sm:inline-flex">
                                    Masuk untuk mengirim
                                </span>
                            </Link>
                        </CardContent>
                    </Card>
                )}

                {items.length === 0 ? (
                    <Card className="border-dashed border-tb-outline-variant bg-tb-surface-bright">
                        <CardContent className="flex flex-col items-center gap-3 py-12 text-center">
                            <Newspaper className="size-10 text-tb-outline" />
                            <div>
                                <p className="font-semibold text-tb-on-surface">
                                    News Feed masih kosong
                                </p>
                                <p className="mt-1 text-sm text-tb-on-surface-variant">
                                    Jadilah orang pertama yang membagikan
                                    status.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    items.map((item) => <FeedCard key={item.key} item={item} />)
                )}

                {hasMore && (
                    <Button
                        variant="outline"
                        className="mx-auto w-fit rounded-full"
                        disabled={loadingMore}
                        onClick={loadMore}
                    >
                        {loadingMore && <Spinner />}
                        {loadingMore ? 'Memuat...' : 'Muat lebih banyak'}
                    </Button>
                )}
            </div>
        </>
    );
}

NewsFeed.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'News Feed', href: newsFeed.index() },
    ],
};
