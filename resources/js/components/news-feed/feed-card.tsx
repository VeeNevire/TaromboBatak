import { Form, Link, router, usePage } from '@inertiajs/react';
import {
    BookOpen,
    CalendarDays,
    ExternalLink,
    Heart,
    Link2,
    Megaphone,
    MessageCircle,
    MoreHorizontal,
    Pencil,
    Send,
    Trash2,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { store as storeFeedComment } from '@/actions/App/Http/Controllers/FeedCommentController';
import { AppAvatar } from '@/components/app-avatar';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { login } from '@/routes';
import newsFeed from '@/routes/news-feed';

export type FeedComment = {
    id: number;
    author: string;
    body: string;
    created_at: string | null;
};

export type FeedItem = {
    key: string;
    type: 'status' | 'story' | 'announcement';
    id: number;
    author: string;
    title: string | null;
    body: string;
    image: string | null;
    url: string | null;
    meta: string | null;
    created_at: string | null;
    comments: FeedComment[];
    audience_label?: string;
    audience?: 'public' | 'marga';
    marga_ids?: number[];
    images?: string[];
    likes_count?: number;
    liked_by_me?: boolean;
    edited?: boolean;
    can?: { update: boolean; delete: boolean };
};

const feedLabels = {
    status: {
        label: 'Status',
        icon: MessageCircle,
        className: 'bg-tb-primary/10 text-tb-primary',
    },
    story: {
        label: 'Cerita',
        icon: BookOpen,
        className: 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
    },
    announcement: {
        label: 'Pengumuman',
        icon: Megaphone,
        className: 'bg-blue-500/10 text-blue-700 dark:text-blue-300',
    },
} as const;

const VISIBLE_COMMENTS = 3;

export function formatFeedDate(value: string | null): string {
    if (!value) {
        return 'Baru saja';
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

async function copyToClipboard(text: string): Promise<boolean> {
    try {
        await navigator.clipboard.writeText(text);

        return true;
    } catch {
        // Clipboard API needs a secure context; fall back to a scratch field.
        const field = document.createElement('textarea');
        field.value = text;
        field.setAttribute('readonly', '');
        field.style.position = 'fixed';
        field.style.opacity = '0';
        document.body.appendChild(field);
        field.select();

        try {
            return document.execCommand('copy');
        } catch {
            return false;
        } finally {
            document.body.removeChild(field);
        }
    }
}

export function FeedCard({ item }: { item: FeedItem }) {
    const { auth } = usePage().props;
    const feedLabel = feedLabels[item.type];
    const TypeIcon = feedLabel.icon;
    const isStatus = item.type === 'status';

    const commentInputRef = useRef<HTMLInputElement>(null);
    const [liked, setLiked] = useState(item.liked_by_me ?? false);
    const [likes, setLikes] = useState(item.likes_count ?? 0);
    const [editing, setEditing] = useState(false);
    const [draft, setDraft] = useState(item.body);
    const [saving, setSaving] = useState(false);
    const [confirmingDelete, setConfirmingDelete] = useState(false);

    const toggleLike = () => {
        const next = !liked;
        const rollback = () => {
            setLiked(!next);
            setLikes((count) => count + (next ? -1 : 1));
        };

        setLiked(next);
        setLikes((count) => count + (next ? 1 : -1));

        const options = {
            preserveScroll: true,
            preserveState: true,
            onError: rollback,
        };

        if (next) {
            router.post(newsFeed.posts.likes.store(item.id).url, {}, options);

            return;
        }

        router.delete(newsFeed.posts.likes.destroy(item.id).url, options);
    };

    const focusComments = () => {
        commentInputRef.current?.scrollIntoView({
            behavior: 'smooth',
            block: 'center',
        });
        commentInputRef.current?.focus();
    };

    const sharePermalink = async () => {
        const target = isStatus
            ? new URL(
                  newsFeed.statuses.show(item.id).url,
                  window.location.origin,
              ).toString()
            : (item.url ?? window.location.href);

        if (await copyToClipboard(target)) {
            toast.success('Tautan disalin.');

            return;
        }

        toast.error('Tautan gagal disalin.');
    };

    const saveEdit = () => {
        setSaving(true);
        router.put(
            newsFeed.posts.update(item.id).url,
            { body: draft, audience: item.audience, marga_ids: item.marga_ids },
            {
                preserveScroll: true,
                onSuccess: () => setEditing(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <Card className="overflow-hidden rounded-xl border-tb-outline-variant bg-tb-surface-bright shadow-sm">
            <CardHeader className="flex-row items-center gap-3 space-y-0 px-4 py-3">
                <AppAvatar name={item.author} />
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="truncate text-sm font-semibold text-tb-on-surface">
                            {item.author}
                        </p>
                        <span
                            className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold ${feedLabel.className}`}
                        >
                            <TypeIcon className="size-3" />
                            {feedLabel.label}
                        </span>
                    </div>
                    <p className="mt-0.5 text-xs text-tb-on-surface-variant">
                        {formatFeedDate(item.created_at)}
                        {item.edited && ' · diedit'}
                        {isStatus &&
                            item.audience_label &&
                            ` · ${item.audience_label}`}
                    </p>
                </div>
                {(item.can?.update || item.can?.delete) && (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-8"
                                aria-label="Opsi postingan"
                            >
                                <MoreHorizontal className="size-5" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {item.can?.update && (
                                <DropdownMenuItem
                                    onSelect={() => {
                                        setDraft(item.body);
                                        setEditing(true);
                                    }}
                                >
                                    <Pencil className="size-4" /> Edit status
                                </DropdownMenuItem>
                            )}
                            {item.can?.delete && (
                                <DropdownMenuItem
                                    variant="destructive"
                                    onSelect={() => setConfirmingDelete(true)}
                                >
                                    <Trash2 className="size-4" /> Hapus status
                                </DropdownMenuItem>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
            </CardHeader>

            <CardContent className="grid gap-3 px-4 pb-3">
                {item.title && (
                    <h2 className="font-display text-lg font-bold text-tb-on-surface">
                        {item.title}
                    </h2>
                )}
                {editing ? (
                    <div className="grid gap-2">
                        <textarea
                            value={draft}
                            onChange={(event) => setDraft(event.target.value)}
                            rows={4}
                            maxLength={2000}
                            aria-label="Ubah isi status"
                            className="w-full resize-none rounded-2xl border border-tb-outline-variant bg-tb-surface-container/40 px-4 py-3 text-sm text-tb-on-surface outline-none focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20"
                        />
                        <div className="flex justify-end gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setEditing(false)}
                            >
                                Batal
                            </Button>
                            <Button
                                size="sm"
                                disabled={saving || draft.trim() === ''}
                                onClick={saveEdit}
                            >
                                {saving ? 'Menyimpan...' : 'Simpan'}
                            </Button>
                        </div>
                    </div>
                ) : (
                    item.body && (
                        <p className="text-sm leading-6 whitespace-pre-line text-tb-on-surface">
                            {item.body}
                        </p>
                    )
                )}
                {item.image && (
                    <img
                        src={item.image}
                        alt={item.title ?? 'Gambar cerita'}
                        className="max-h-[560px] w-full rounded-lg border border-tb-outline-variant object-cover"
                        onError={(event) => {
                            event.currentTarget.hidden = true;
                        }}
                    />
                )}
                {item.images && item.images.length > 0 && (
                    <div
                        className={cn(
                            'grid gap-2',
                            item.images.length > 1 && 'grid-cols-2',
                        )}
                    >
                        {item.images.map((src) => (
                            <img
                                key={src}
                                src={src}
                                alt="Lampiran status"
                                loading="lazy"
                                className={cn(
                                    'w-full rounded-lg border border-tb-outline-variant object-cover',
                                    item.images!.length === 1
                                        ? 'max-h-[560px]'
                                        : 'aspect-square',
                                )}
                            />
                        ))}
                    </div>
                )}
                {item.meta && (
                    <p className="flex items-center gap-1.5 text-xs font-medium text-tb-on-surface-variant">
                        <CalendarDays className="size-3.5" />
                        {item.meta}
                    </p>
                )}
            </CardContent>

            <div className="flex items-center gap-1 px-3 pb-2">
                {isStatus &&
                    (auth.user ? (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="gap-1.5"
                            aria-label={liked ? 'Batal suka' : 'Suka'}
                            aria-pressed={liked}
                            onClick={toggleLike}
                        >
                            <Heart
                                className={cn(
                                    'size-5',
                                    liked && 'fill-current text-rose-500',
                                )}
                            />
                            {likes > 0 && (
                                <span className="text-xs font-medium">
                                    {likes}
                                </span>
                            )}
                        </Button>
                    ) : (
                        <Button
                            asChild
                            variant="ghost"
                            size="sm"
                            className="gap-1.5"
                        >
                            <Link
                                href={login()}
                                aria-label="Masuk untuk menyukai"
                            >
                                <Heart className="size-5" />
                                {likes > 0 && (
                                    <span className="text-xs font-medium">
                                        {likes}
                                    </span>
                                )}
                            </Link>
                        </Button>
                    ))}
                {isStatus && (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="gap-1.5"
                        aria-label="Komentar"
                        onClick={focusComments}
                    >
                        <MessageCircle className="size-5" />
                        {item.comments.length > 0 && (
                            <span className="text-xs font-medium">
                                {item.comments.length}
                            </span>
                        )}
                    </Button>
                )}
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-9"
                    aria-label="Bagikan"
                    onClick={sharePermalink}
                >
                    <Send className="size-5" />
                </Button>
            </div>

            {isStatus ? (
                <StatusComments
                    postId={item.id}
                    comments={item.comments}
                    inputRef={commentInputRef}
                />
            ) : (
                item.url && (
                    <CardFooter className="border-t border-tb-outline-variant px-4 pt-3 pb-4">
                        <Button asChild variant="outline" size="sm">
                            <a
                                href={item.url}
                                target="_blank"
                                rel="noreferrer noopener"
                            >
                                Lihat selengkapnya
                                <ExternalLink className="size-3.5" />
                            </a>
                        </Button>
                    </CardFooter>
                )
            )}

            <Dialog
                open={confirmingDelete}
                onOpenChange={(open) => !open && setConfirmingDelete(false)}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Hapus Status</DialogTitle>
                        <DialogDescription>
                            Status ini beserta komentar dan gambarnya akan
                            dihapus permanen.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmingDelete(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() =>
                                router.delete(
                                    newsFeed.posts.destroy(item.id).url,
                                    {
                                        preserveScroll: true,
                                        onSuccess: () =>
                                            setConfirmingDelete(false),
                                    },
                                )
                            }
                        >
                            Ya, Hapus
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </Card>
    );
}

function StatusComments({
    postId,
    comments,
    inputRef,
}: {
    postId: number;
    comments: FeedComment[];
    inputRef: React.RefObject<HTMLInputElement | null>;
}) {
    const { auth } = usePage().props;
    const [showAll, setShowAll] = useState(false);
    const hidden = Math.max(0, comments.length - VISIBLE_COMMENTS);
    const visible = showAll ? comments : comments.slice(-VISIBLE_COMMENTS);

    return (
        <CardFooter className="grid gap-3 border-t border-tb-outline-variant bg-tb-surface-container/20 px-4 pt-3 pb-4">
            {hidden > 0 && !showAll && (
                <button
                    type="button"
                    onClick={() => setShowAll(true)}
                    className="w-fit text-xs font-medium text-tb-primary hover:underline"
                >
                    Lihat semua {comments.length} komentar
                </button>
            )}
            {visible.length > 0 && (
                <div className="grid gap-3">
                    {visible.map((comment) => (
                        <div
                            key={comment.id}
                            className="flex items-start gap-2.5"
                        >
                            <AppAvatar
                                name={comment.author}
                                className="size-8"
                            />
                            <div className="min-w-0 rounded-xl bg-tb-surface-container px-3 py-2">
                                <div className="flex flex-wrap items-baseline gap-x-2">
                                    <p className="text-xs font-semibold text-tb-on-surface">
                                        {comment.author}
                                    </p>
                                    <p className="text-[10px] text-tb-on-surface-variant">
                                        {formatFeedDate(comment.created_at)}
                                    </p>
                                </div>
                                <p className="mt-0.5 text-sm leading-5 whitespace-pre-line text-tb-on-surface">
                                    {comment.body}
                                </p>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {auth.user ? (
                <Form
                    {...storeFeedComment.form(postId)}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                >
                    {({ errors, processing }) => (
                        <div className="grid gap-1.5">
                            <div className="flex items-center gap-2.5">
                                <AppAvatar
                                    name={auth.user.name}
                                    className="size-8"
                                />
                                <input
                                    ref={inputRef}
                                    name="body"
                                    maxLength={500}
                                    required
                                    placeholder="Tulis komentar..."
                                    className="h-9 min-w-0 flex-1 rounded-full border border-tb-outline-variant bg-tb-surface-bright px-4 text-sm text-tb-on-surface outline-none placeholder:text-tb-on-surface-variant focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20"
                                />
                                <Button
                                    type="submit"
                                    size="icon"
                                    className="size-9 rounded-full"
                                    disabled={processing}
                                    aria-label="Kirim komentar"
                                >
                                    <Send className="size-4" />
                                </Button>
                            </div>
                            <InputError
                                message={errors.body}
                                className="ml-11"
                            />
                        </div>
                    )}
                </Form>
            ) : (
                <Link
                    href={login()}
                    className="inline-flex w-fit items-center gap-1.5 text-xs font-medium text-tb-primary hover:underline"
                >
                    <Link2 className="size-3.5" /> Masuk untuk berkomentar
                </Link>
            )}
        </CardFooter>
    );
}
