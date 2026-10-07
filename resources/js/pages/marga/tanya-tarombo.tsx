import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    BookOpen,
    BookPlus,
    Bot,
    LoaderCircle,
    Pencil,
    RotateCcw,
    Save,
    Search,
    Send,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import margaRoutes from '@/routes/marga';

type Message = { id: number; role: 'user' | 'assistant'; text: string };
type Marga = {
    id: number;
    name: string;
    color?: string | null;
    description?: string | null;
};
type LibraryLesson = {
    id: number;
    marga_id: number | null;
    marga_name: string | null;
    title: string;
    topic: string;
    content: string;
    is_active: boolean;
    updated_at: string | null;
};

export default function TanyaTarombo({
    marga,
    margas,
    messages,
    conversationId,
    conversations,
    libraryLessons,
    canManageLibrary,
}: {
    marga: Marga | null;
    margas: Marga[];
    messages: Message[];
    conversationId: number;
    conversations: { id: number; created_at: string }[];
    libraryLessons: LibraryLesson[];
    canManageLibrary: boolean;
}) {
    const form = useForm({ question: '', conversation_id: conversationId });
    const [tab, setTab] = useState<'chat' | 'margas' | 'library'>('chat');
    const [search, setSearch] = useState('');
    const [librarySearch, setLibrarySearch] = useState('');
    const [editingLessonId, setEditingLessonId] = useState<number | null>(null);
    const [pendingQuestion, setPendingQuestion] = useState('');
    const [startingNew, setStartingNew] = useState(false);
    const libraryForm = useForm({
        marga_id: marga?.id.toString() ?? '',
        title: '',
        topic: 'Relasi generasi',
        content: '',
        is_active: true,
    });
    const bottomRef = useRef<HTMLDivElement>(null);
    const busy = form.processing || startingNew;
    const filteredMargas = margas.filter((row) =>
        row.name
            .toLocaleLowerCase('id')
            .includes(search.trim().toLocaleLowerCase('id')),
    );
    const filteredLibraryLessons = libraryLessons.filter((lesson) =>
        `${lesson.title} ${lesson.topic} ${lesson.marga_name ?? ''} ${lesson.content}`
            .toLocaleLowerCase('id')
            .includes(librarySearch.trim().toLocaleLowerCase('id')),
    );
    useEffect(() => {
        bottomRef.current?.scrollIntoView({ block: 'nearest' });
    }, [messages.length, pendingQuestion, tab]);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const question = form.data.question.trim();

        if (!question || busy) {
            return;
        }

        setPendingQuestion(question);
        form.transform((data) => ({
            ...data,
            question,
            conversation_id: conversationId,
        }));
        form.post(
            marga
                ? margaRoutes.ai.ask(marga.id).url
                : margaRoutes.ai.askAll().url,
            {
                preserveScroll: true,
                onSuccess: () => form.reset('question'),
                onFinish: () => setPendingQuestion(''),
            },
        );
    };
    const startNew = () => {
        if (busy) {
            return;
        }

        setStartingNew(true);
        router.post(
            marga
                ? margaRoutes.ai.newConversation(marga.id).url
                : margaRoutes.ai.newGeneralConversation().url,
            {},
            {
                preserveState: false,
                onFinish: () => setStartingNew(false),
            },
        );
    };
    const openConversation = (id: number) => {
        router.get(
            marga
                ? margaRoutes.ai.show(marga.id, { query: { conversation: id } })
                      .url
                : margaRoutes.ai.select({ query: { conversation: id } }).url,
        );
    };
    const resetLibraryForm = () => {
        setEditingLessonId(null);
        libraryForm.reset();
        libraryForm.clearErrors();
    };
    const submitLibraryLesson = (event: FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: resetLibraryForm,
        };

        if (editingLessonId) {
            libraryForm.patch(
                margaRoutes.ai.lessons.update(editingLessonId).url,
                options,
            );
        } else {
            libraryForm.post(margaRoutes.ai.lessons.store().url, options);
        }
    };
    const editLesson = (lesson: LibraryLesson) => {
        setEditingLessonId(lesson.id);
        libraryForm.setData({
            marga_id: lesson.marga_id?.toString() ?? '',
            title: lesson.title,
            topic: lesson.topic,
            content: lesson.content,
            is_active: lesson.is_active,
        });
        libraryForm.clearErrors();
    };
    const deleteLesson = (lesson: LibraryLesson) => {
        if (
            !window.confirm(`Hapus materi "${lesson.title}" dari pustaka AI?`)
        ) {
            return;
        }

        router.delete(margaRoutes.ai.lessons.destroy(lesson.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                if (editingLessonId === lesson.id) {
                    resetLibraryForm();
                }
            },
        });
    };

    return (
        <>
            <Head title={`Tanya Ito Tarombo · ${marga?.name ?? 'Semua'}`} />
            <div className="flex min-h-full flex-1 flex-col gap-6 bg-tb-surface p-4 text-tb-on-surface md:p-6 lg:p-8">
                <div>
                    {marga && (
                        <Link
                            href={margaRoutes.documents.index(marga.id)}
                            className="text-sm text-tb-primary"
                        >
                            ← Dokumen {marga.name}
                        </Link>
                    )}
                    <h1 className="mt-2 flex items-center gap-2 font-display text-2xl font-bold">
                        <Bot className="size-6 text-tb-primary" /> Tanya Ito
                        Tarombo
                    </h1>
                    <p className="mt-1 text-sm text-tb-on-surface-variant">
                        {marga
                            ? `Tanyakan hal seputar tarombo dan marga ${marga.name}.`
                            : 'Tanyakan hal seputar tarombo dan semua marga yang dapat Anda akses.'}{' '}
                        Percakapan tersimpan di akun Anda.
                    </p>
                </div>
                <nav
                    aria-label="Navigasi Tanya Ito Tarombo"
                    className="flex flex-wrap gap-2 border-b border-tb-outline-variant pb-3"
                >
                    {marga ? (
                        <Button
                            type="button"
                            variant={tab === 'chat' ? 'default' : 'outline'}
                            disabled={busy}
                            onClick={() => setTab('chat')}
                        >
                            Tanya {marga.name}
                        </Button>
                    ) : (
                        <Button
                            asChild
                            variant={tab === 'chat' ? 'default' : 'outline'}
                            disabled={busy}
                        >
                            <Link href={margaRoutes.ai.select()}>Semua</Link>
                        </Button>
                    )}
                    <Button
                        type="button"
                        variant={tab === 'margas' ? 'default' : 'outline'}
                        disabled={busy}
                        onClick={() => setTab('margas')}
                    >
                        Marga-Marga
                    </Button>
                    <Button
                        type="button"
                        variant={tab === 'library' ? 'default' : 'outline'}
                        disabled={busy}
                        onClick={() => setTab('library')}
                    >
                        <BookOpen className="size-4" /> Pustaka (Library) AI
                    </Button>
                </nav>
                {tab === 'library' ? (
                    <section className="space-y-5">
                        <div>
                            <h2 className="font-display text-xl font-bold">
                                Materi pembelajaran Ito Tarombo
                            </h2>
                            <p className="mt-1 max-w-3xl text-sm text-tb-on-surface-variant">
                                Ajarkan cara membaca tingkat generasi,
                                membedakan hubungan saudara sekandung maupun
                                tidak sekandung, serta arti istilah tarombo.
                                Materi aktif akan digunakan Ito saat menjawab
                                pertanyaan.
                            </p>
                        </div>

                        {canManageLibrary && (
                            <Card className="rounded-2xl border-tb-outline-variant bg-tb-surface-bright shadow-sm">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        {editingLessonId ? (
                                            <Pencil className="size-5" />
                                        ) : (
                                            <BookPlus className="size-5" />
                                        )}
                                        {editingLessonId
                                            ? 'Ubah materi pembelajaran'
                                            : 'Ajarkan materi baru'}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <form
                                        onSubmit={submitLibraryLesson}
                                        className="space-y-4"
                                    >
                                        <div className="grid gap-4 md:grid-cols-3">
                                            <div className="space-y-2">
                                                <Label htmlFor="lesson-title">
                                                    Judul materi
                                                </Label>
                                                <Input
                                                    id="lesson-title"
                                                    required
                                                    maxLength={160}
                                                    value={
                                                        libraryForm.data.title
                                                    }
                                                    onChange={(event) =>
                                                        libraryForm.setData(
                                                            'title',
                                                            event.target.value,
                                                        )
                                                    }
                                                    placeholder="Contoh: Menentukan tingkat generasi"
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="lesson-topic">
                                                    Topik
                                                </Label>
                                                <select
                                                    id="lesson-topic"
                                                    value={
                                                        libraryForm.data.topic
                                                    }
                                                    onChange={(event) =>
                                                        libraryForm.setData(
                                                            'topic',
                                                            event.target.value,
                                                        )
                                                    }
                                                    className="h-10 w-full rounded-md border border-tb-outline-variant bg-tb-surface-bright px-3 text-sm"
                                                >
                                                    {[
                                                        'Relasi generasi',
                                                        'Hubungan saudara',
                                                        'Istilah tarombo',
                                                        'Aturan membaca pohon',
                                                        'Lainnya',
                                                    ].map((topic) => (
                                                        <option
                                                            key={topic}
                                                            value={topic}
                                                        >
                                                            {topic}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="lesson-marga">
                                                    Cakupan materi
                                                </Label>
                                                <select
                                                    id="lesson-marga"
                                                    value={
                                                        libraryForm.data
                                                            .marga_id
                                                    }
                                                    onChange={(event) =>
                                                        libraryForm.setData(
                                                            'marga_id',
                                                            event.target.value,
                                                        )
                                                    }
                                                    className="h-10 w-full rounded-md border border-tb-outline-variant bg-tb-surface-bright px-3 text-sm"
                                                >
                                                    <option value="">
                                                        Semua marga
                                                    </option>
                                                    {margas.map((row) => (
                                                        <option
                                                            key={row.id}
                                                            value={row.id}
                                                        >
                                                            {row.name}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="lesson-content">
                                                Isi pelajaran
                                            </Label>
                                            <textarea
                                                id="lesson-content"
                                                required
                                                minLength={20}
                                                maxLength={12000}
                                                rows={8}
                                                value={libraryForm.data.content}
                                                onChange={(event) =>
                                                    libraryForm.setData(
                                                        'content',
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder={
                                                    'Tuliskan aturan dan contoh secara jelas. Contoh susunan:\n\nTingkat generasi: jelaskan cara menghitung jarak orang tua-anak.\nHubungan saudara: jelaskan syarat sekandung, seayah, atau seibu; tandai data yang harus tersedia.\nIstilah tarombo: tulis istilah, arti, contoh penggunaan, dan marga/daerah yang menggunakannya.'
                                                }
                                                className="min-h-48 w-full rounded-md border border-tb-outline-variant bg-tb-surface-bright px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-tb-primary"
                                            />
                                            <p className="text-xs text-tb-on-surface-variant">
                                                Materi 20–12.000 karakter.
                                                Tambahkan contoh relasi dan
                                                variasi istilah lokal agar Ito
                                                tahu kapan suatu definisi
                                                berlaku.
                                            </p>
                                            {libraryForm.errors.content && (
                                                <p className="text-sm text-destructive">
                                                    {libraryForm.errors.content}
                                                </p>
                                            )}
                                        </div>
                                        <label className="flex items-center gap-2 text-sm">
                                            <input
                                                type="checkbox"
                                                checked={
                                                    libraryForm.data.is_active
                                                }
                                                onChange={(event) =>
                                                    libraryForm.setData(
                                                        'is_active',
                                                        event.target.checked,
                                                    )
                                                }
                                                className="size-4 accent-tb-primary"
                                            />
                                            Aktifkan materi ini untuk jawaban
                                            Ito
                                        </label>
                                        {(libraryForm.errors.title ||
                                            libraryForm.errors.topic ||
                                            libraryForm.errors.marga_id ||
                                            libraryForm.errors.is_active) && (
                                            <p className="text-sm text-destructive">
                                                {libraryForm.errors.title ||
                                                    libraryForm.errors.topic ||
                                                    libraryForm.errors
                                                        .marga_id ||
                                                    libraryForm.errors
                                                        .is_active}
                                            </p>
                                        )}
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                type="submit"
                                                disabled={
                                                    libraryForm.processing
                                                }
                                            >
                                                {libraryForm.processing ? (
                                                    <LoaderCircle className="size-4 animate-spin" />
                                                ) : (
                                                    <Save className="size-4" />
                                                )}
                                                {editingLessonId
                                                    ? 'Simpan perubahan'
                                                    : 'Simpan materi'}
                                            </Button>
                                            {editingLessonId && (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    onClick={resetLibraryForm}
                                                >
                                                    <X className="size-4" />{' '}
                                                    Batal mengubah
                                                </Button>
                                            )}
                                        </div>
                                    </form>
                                </CardContent>
                            </Card>
                        )}

                        <Card className="rounded-2xl border-tb-outline-variant bg-tb-surface-bright shadow-sm">
                            <CardHeader>
                                <CardTitle>
                                    Materi pustaka ·{' '}
                                    {marga?.name ??
                                        'semua marga yang dapat diakses'}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {libraryLessons.length > 0 && (
                                    <div className="relative mb-4 max-w-xl">
                                        <Search className="pointer-events-none absolute top-2.5 left-3 size-4 text-tb-on-surface-variant" />
                                        <Input
                                            aria-label="Cari materi pembelajaran"
                                            type="search"
                                            value={librarySearch}
                                            onChange={(event) =>
                                                setLibrarySearch(
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Cari judul, topik, istilah, atau isi materi…"
                                            className="pl-9"
                                        />
                                    </div>
                                )}
                                {libraryLessons.length === 0 ? (
                                    <div className="py-10 text-center">
                                        <BookOpen className="mx-auto size-8 text-tb-on-surface-variant" />
                                        <p className="mt-3 font-medium">
                                            Belum ada materi pembelajaran
                                        </p>
                                        <p className="mt-1 text-sm text-tb-on-surface-variant">
                                            Admin dapat menambahkan pelajaran
                                            langsung lewat textbox di atas.
                                        </p>
                                    </div>
                                ) : (
                                    <div className="divide-y divide-tb-outline-variant">
                                        {filteredLibraryLessons.map(
                                            (lesson) => (
                                                <article
                                                    key={lesson.id}
                                                    className="space-y-3 py-4"
                                                >
                                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                                        <div className="min-w-0">
                                                            <p className="font-semibold">
                                                                {lesson.title}
                                                            </p>
                                                            <p className="mt-1 text-xs text-tb-on-surface-variant">
                                                                {lesson.topic} ·{' '}
                                                                {lesson.marga_name ??
                                                                    'Semua marga'}
                                                                {lesson.updated_at
                                                                    ? ` · Diperbarui ${lesson.updated_at}`
                                                                    : ''}
                                                            </p>
                                                        </div>
                                                        <div className="flex items-center gap-2">
                                                            <span
                                                                className={`rounded-full px-2 py-1 text-xs ${lesson.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-tb-surface text-tb-on-surface-variant'}`}
                                                            >
                                                                {lesson.is_active
                                                                    ? 'Aktif untuk Ito'
                                                                    : 'Nonaktif'}
                                                            </span>
                                                            {canManageLibrary && (
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    aria-label={`Ubah ${lesson.title}`}
                                                                    onClick={() =>
                                                                        editLesson(
                                                                            lesson,
                                                                        )
                                                                    }
                                                                >
                                                                    <Pencil className="size-4" />
                                                                </Button>
                                                            )}
                                                            {canManageLibrary && (
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    aria-label={`Hapus ${lesson.title}`}
                                                                    onClick={() =>
                                                                        deleteLesson(
                                                                            lesson,
                                                                        )
                                                                    }
                                                                >
                                                                    <Trash2 className="size-4 text-destructive" />
                                                                </Button>
                                                            )}
                                                        </div>
                                                    </div>
                                                    <p className="text-sm leading-relaxed whitespace-pre-wrap text-tb-on-surface-variant">
                                                        {lesson.content}
                                                    </p>
                                                </article>
                                            ),
                                        )}
                                        {filteredLibraryLessons.length ===
                                            0 && (
                                            <p className="py-8 text-center text-sm text-tb-on-surface-variant">
                                                Tidak ada materi yang cocok
                                                dengan pencarian.
                                            </p>
                                        )}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </section>
                ) : tab === 'margas' ? (
                    <section className="space-y-4">
                        <h2 className="font-display text-xl font-bold">
                            Pilih marga untuk bertanya
                        </h2>
                        <div className="relative max-w-xl">
                            <Search className="pointer-events-none absolute top-2.5 left-3 size-4 text-tb-on-surface-variant" />
                            <Input
                                aria-label="Cari marga"
                                type="search"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Cari marga…"
                                className="pl-9"
                            />
                        </div>
                        <p
                            className="text-sm text-tb-on-surface-variant"
                            role="status"
                        >
                            {filteredMargas.length} marga ditemukan
                        </p>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {filteredMargas.map((row) => (
                                <Link
                                    key={row.id}
                                    href={margaRoutes.ai.show(row.id)}
                                    className="rounded-2xl border border-tb-outline-variant bg-tb-surface-bright p-5 font-medium shadow-sm transition hover:border-tb-primary focus-visible:ring-2 focus-visible:ring-tb-primary"
                                >
                                    {row.name}
                                </Link>
                            ))}
                            {filteredMargas.length === 0 && (
                                <p className="col-span-full py-8 text-center text-sm text-tb-on-surface-variant">
                                    Marga tidak ditemukan.
                                </p>
                            )}
                        </div>
                    </section>
                ) : (
                    <Card className="flex min-h-[55vh] flex-1 flex-col rounded-2xl border-tb-outline-variant bg-tb-surface-bright shadow-sm">
                        <CardHeader className="flex flex-wrap items-center justify-between gap-3 sm:flex-row">
                            <CardTitle className="text-lg">
                                Percakapan · {marga?.name ?? 'Semua'}
                            </CardTitle>
                            <div className="flex flex-wrap items-center gap-2">
                                {conversations.length > 1 && (
                                    <select
                                        aria-label="Riwayat percakapan"
                                        value={conversationId}
                                        disabled={busy}
                                        onChange={(event) =>
                                            openConversation(
                                                Number(event.target.value),
                                            )
                                        }
                                        className="h-9 max-w-56 rounded-md border border-tb-outline-variant bg-tb-surface-bright px-2 text-sm"
                                    >
                                        {conversations.map((conversation) => (
                                            <option
                                                key={conversation.id}
                                                value={conversation.id}
                                            >
                                                Percakapan {conversation.id} ·{' '}
                                                {new Date(
                                                    conversation.created_at,
                                                ).toLocaleDateString('id-ID')}
                                            </option>
                                        ))}
                                    </select>
                                )}
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={busy}
                                    onClick={startNew}
                                >
                                    <RotateCcw className="size-4" /> Percakapan
                                    baru
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent className="flex flex-1 flex-col gap-4">
                            <div
                                className="max-h-[60vh] min-h-64 flex-1 space-y-3 overflow-y-auto rounded-xl bg-tb-surface p-4"
                                role="log"
                                aria-label="Pesan percakapan"
                                aria-live="polite"
                            >
                                {messages.length === 0 && !pendingQuestion && (
                                    <p className="py-16 text-center text-sm text-tb-on-surface-variant">
                                        Mulai dengan pertanyaan tentang{' '}
                                        {marga?.name ?? 'tarombo dan marga'}.
                                    </p>
                                )}
                                {messages.map((message) => (
                                    <div
                                        key={message.id}
                                        className={`w-fit max-w-[90%] rounded-2xl px-4 py-3 text-sm leading-relaxed whitespace-pre-wrap sm:max-w-[75%] ${message.role === 'user' ? 'ml-auto bg-tb-primary text-white' : 'bg-tb-surface-bright text-tb-on-surface'}`}
                                    >
                                        {message.text}
                                    </div>
                                ))}
                                {pendingQuestion && (
                                    <>
                                        <div className="ml-auto w-fit max-w-[90%] rounded-2xl bg-tb-primary px-4 py-3 text-sm whitespace-pre-wrap text-white sm:max-w-[75%]">
                                            {pendingQuestion}
                                        </div>
                                        <p className="flex items-center gap-2 text-sm text-tb-on-surface-variant">
                                            <LoaderCircle className="size-4 animate-spin" />{' '}
                                            Ito Tarombo sedang menjawab…
                                        </p>
                                    </>
                                )}
                                <div ref={bottomRef} />
                            </div>
                            <form onSubmit={submit} className="flex gap-2">
                                <textarea
                                    aria-label="Pertanyaan untuk Ito Tarombo"
                                    className="min-h-16 min-w-0 flex-1 resize-none rounded-md border border-tb-outline-variant bg-tb-surface-bright px-3 py-2 text-sm"
                                    value={form.data.question}
                                    onChange={(event) =>
                                        form.setData(
                                            'question',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Tulis pertanyaan..."
                                    rows={2}
                                    maxLength={4000}
                                    disabled={busy}
                                />
                                <Button
                                    type="submit"
                                    size="icon"
                                    disabled={
                                        busy || !form.data.question.trim()
                                    }
                                    aria-label="Kirim pertanyaan"
                                >
                                    <Send className="size-4" />
                                </Button>
                            </form>
                            {form.errors.question && (
                                <p className="text-sm text-destructive">
                                    {form.errors.question}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

TanyaTarombo.layout = {
    breadcrumbs: [
        { title: 'Tanya Ito Tarombo', href: margaRoutes.ai.select() },
    ],
};
