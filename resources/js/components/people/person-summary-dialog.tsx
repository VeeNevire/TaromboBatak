import { Link, router } from '@inertiajs/react';
import {
    BookOpen,
    CalendarDays,
    Copy,
    ExternalLink,
    GitBranch,
    MapPin,
    LoaderCircle,
    Pencil,
    UserPlus,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { FamilyBranchDialog } from '@/components/people/family-branch-dialog';
import { PersonImage } from '@/components/people/person-image';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { TaromboPerson } from '@/data/tarombo-tree';
import contactRequests from '@/routes/contact-requests';
import contacts from '@/routes/contacts';
import margaBranchEntries from '@/routes/marga-branch-entries';
import peopleRoutes from '@/routes/people';

function yearOnly(value?: string | null): string {
    if (!value) {
        return 'Belum dicatat';
    }

    return value.match(/\b\d{4}\b/)?.[0] ?? value;
}

export function PersonSummaryDialog({
    person,
    people,
    onClose,
    currentUserId,
    versionTreeId,
    allowBranchEntry = false,
}: {
    person: TaromboPerson | null;
    people: TaromboPerson[];
    onClose: () => void;
    currentUserId?: number;
    versionTreeId?: number | null;
    allowBranchEntry?: boolean;
}) {
    const [connectingAccountId, setConnectingAccountId] = useState<
        number | null
    >(null);
    const [branchDialogOpen, setBranchDialogOpen] = useState(false);
    const father = person?.parentId
        ? people.find((candidate) => candidate.id === person.parentId)
        : undefined;
    const children = person
        ? (person.childrenNames ??
          people
              .filter((candidate) => candidate.parentId === person.id)
              .map((child) => child.name))
        : [];
    const connectableAccounts = (person?.claimedAccounts ?? []).filter(
        (account) =>
            account.id !== currentUserId &&
            account.role !== 'admin' &&
            !account.isContact,
    );
    const canAddBranch =
        person !== null &&
        person.gender !== 'P' &&
        versionTreeId != null &&
        person.treeNodeId != null &&
        (person.childrenNames?.length ?? 0) === 0;

    const connect = (accountId: number) => {
        setConnectingAccountId(accountId);

        router.post(
            contactRequests.store().url,
            { recipient_id: accountId },
            {
                preserveScroll: true,
                onSuccess: () => {
                    onClose();
                    router.get(contacts.index().url);
                },
                onFinish: () => setConnectingAccountId(null),
            },
        );
    };

    const copyPersonCode = async () => {
        if (!person?.shareCode) {
            return;
        }

        try {
            await navigator.clipboard.writeText(person.shareCode);
            toast.success(
                'Kode orang berhasil disalin. Kirimkan kode ini lewat pesan.',
            );
        } catch {
            toast.error(
                'Kode tidak dapat disalin. Periksa izin clipboard browser.',
            );
        }
    };

    return (
        <Dialog
            open={person !== null}
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            {person && (
                <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto border-tb-outline-variant bg-tb-surface-bright sm:max-w-lg">
                    <DialogHeader className="items-center text-center sm:text-center">
                        <div className="flex size-16 items-center justify-center overflow-hidden rounded-2xl border border-tb-outline-variant bg-tb-surface-container text-sm font-bold text-tb-on-surface-variant shadow-sm">
                            <PersonImage
                                src={person.image}
                                name={person.name}
                                className="size-full object-cover"
                            />
                        </div>
                        <div>
                            <DialogTitle className="font-display text-xl text-tb-on-surface">
                                {person.name}
                            </DialogTitle>
                            <DialogDescription className="mt-1 text-tb-on-surface-variant">
                                Informasi anggota keluarga
                            </DialogDescription>
                        </div>
                    </DialogHeader>

                    <div className="grid gap-4 text-sm">
                        <section className="rounded-xl border border-tb-outline-variant bg-tb-surface-container/50 p-4">
                            <h3 className="mb-3 flex items-center gap-2 font-semibold text-tb-on-surface">
                                <UserRound className="size-4 text-tb-primary" />
                                Informasi Pribadi
                            </h3>
                            <dl className="grid gap-3">
                                <div className="flex items-start justify-between gap-4">
                                    <dt className="flex items-center gap-1.5 text-tb-on-surface-variant">
                                        <CalendarDays className="size-3.5" />
                                        Tahun lahir
                                    </dt>
                                    <dd className="text-right font-medium text-tb-on-surface">
                                        {yearOnly(person.birthYear)}
                                    </dd>
                                </div>
                                <div className="flex items-start justify-between gap-4">
                                    <dt className="text-tb-on-surface-variant">
                                        Marga
                                    </dt>
                                    <dd className="text-right font-medium text-tb-on-surface">
                                        {person.marga || 'Belum dicatat'}
                                    </dd>
                                </div>
                                {person.alias && (
                                    <div className="flex items-start justify-between gap-4">
                                        <dt className="text-tb-on-surface-variant">
                                            Alias
                                        </dt>
                                        <dd className="text-right font-medium text-tb-on-surface">
                                            {person.alias}
                                        </dd>
                                    </div>
                                )}
                                {person.location &&
                                    Object.values(person.location).some(
                                        Boolean,
                                    ) && (
                                        <div className="flex items-start justify-between gap-4">
                                            <dt className="flex items-center gap-1.5 text-tb-on-surface-variant">
                                                <MapPin className="size-3.5" />
                                                Lokasi
                                            </dt>
                                            <dd className="max-w-[65%] text-right font-medium text-tb-on-surface">
                                                {[
                                                    person.location.village,
                                                    person.location.district,
                                                    person.location.regency,
                                                    person.location.province,
                                                ]
                                                    .filter(Boolean)
                                                    .join(', ')}
                                            </dd>
                                        </div>
                                    )}
                            </dl>
                        </section>

                        <section className="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4 dark:border-emerald-800 dark:bg-emerald-950/20">
                            <h3 className="flex items-center gap-2 font-semibold text-emerald-800 dark:text-emerald-300">
                                <UserPlus className="size-4" /> Terhubung
                            </h3>
                            {(person.claimedAccounts?.length ?? 0) > 0 ? (
                                <>
                                    <p className="mt-1 text-sm text-tb-on-surface-variant">
                                        Nama ini sudah diklaim oleh akun Tarombo
                                        Batak.
                                    </p>
                                    <div className="mt-3 grid gap-2">
                                        {person.claimedAccounts?.map(
                                            (account) => {
                                                const canConnect =
                                                    connectableAccounts.some(
                                                        (candidate) =>
                                                            candidate.id ===
                                                            account.id,
                                                    );

                                                return (
                                                    <div
                                                        key={account.id}
                                                        className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-emerald-200/80 bg-tb-surface-bright px-3 py-2 dark:border-emerald-900"
                                                    >
                                                        <div className="flex min-w-0 items-center gap-2">
                                                            <span
                                                                aria-hidden
                                                                className={`size-2 shrink-0 rounded-full ${account.isContact ? 'bg-emerald-500' : 'bg-tb-outline'}`}
                                                            />
                                                            <span className="truncate text-sm font-medium text-tb-on-surface">
                                                                {account.name}
                                                            </span>
                                                            <span
                                                                className={`text-xs font-medium ${account.isContact ? 'text-emerald-700 dark:text-emerald-300' : 'text-tb-outline'}`}
                                                            >
                                                                {account.isContact
                                                                    ? 'Connected'
                                                                    : 'Not Connected'}
                                                            </span>
                                                        </div>
                                                        {canConnect && (
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                onClick={() =>
                                                                    connect(
                                                                        account.id,
                                                                    )
                                                                }
                                                                disabled={
                                                                    connectingAccountId !==
                                                                    null
                                                                }
                                                                className="bg-emerald-700 text-white hover:bg-emerald-800"
                                                            >
                                                                {connectingAccountId ===
                                                                account.id ? (
                                                                    <LoaderCircle className="size-4 animate-spin" />
                                                                ) : (
                                                                    <UserPlus className="size-4" />
                                                                )}
                                                                Connect
                                                            </Button>
                                                        )}
                                                    </div>
                                                );
                                            },
                                        )}
                                    </div>
                                </>
                            ) : (
                                <div className="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-emerald-200/80 bg-tb-surface-bright px-3 py-2 dark:border-emerald-900">
                                    <div className="flex items-center gap-2">
                                        <span
                                            aria-hidden
                                            className="size-2 rounded-full bg-tb-outline"
                                        />
                                        <span className="text-sm font-medium text-tb-outline">
                                            Not Connected
                                        </span>
                                    </div>
                                    <Button
                                        type="button"
                                        size="sm"
                                        onClick={() =>
                                            router.get(contacts.index().url)
                                        }
                                        className="bg-emerald-700 text-white hover:bg-emerald-800"
                                    >
                                        <UserPlus className="size-4" /> Connect
                                    </Button>
                                </div>
                            )}
                        </section>

                        {person.bio && (
                            <section className="rounded-xl border border-tb-outline-variant bg-tb-surface-container/50 p-4">
                                <h3 className="mb-2 font-semibold text-tb-on-surface">
                                    Biografi
                                </h3>
                                <p className="leading-relaxed whitespace-pre-line text-tb-on-surface-variant">
                                    {person.bio}
                                </p>
                            </section>
                        )}

                        <section className="rounded-xl border border-tb-outline-variant bg-tb-surface-container/50 p-4">
                            <h3 className="mb-3 flex items-center gap-2 font-semibold text-tb-on-surface">
                                <GitBranch className="size-4 text-tb-primary" />
                                Hubungan Keluarga
                            </h3>
                            <dl className="grid gap-3">
                                <div className="flex items-start justify-between gap-4">
                                    <dt className="text-tb-on-surface-variant">
                                        Ayah
                                    </dt>
                                    <dd className="text-right font-medium text-tb-on-surface">
                                        {father?.name ?? 'Belum dicatat'}
                                    </dd>
                                </div>
                                <div className="flex items-start justify-between gap-4">
                                    <dt className="text-tb-on-surface-variant">
                                        Pasangan
                                    </dt>
                                    <dd className="flex flex-col items-end gap-1 text-right font-medium">
                                        {(person.spouses ?? []).length > 0 ? (
                                            person.spouses?.map((spouse) => (
                                                <Link
                                                    key={spouse.id}
                                                    href={peopleRoutes.silsilah(
                                                        {
                                                            person: Number(
                                                                spouse.id,
                                                            ),
                                                        },
                                                        {
                                                            query: {
                                                                context:
                                                                    'close',
                                                            },
                                                        },
                                                    )}
                                                    className="text-tb-primary underline decoration-tb-primary/40 underline-offset-2 transition-colors hover:text-tb-primary-light"
                                                >
                                                    {spouse.name}
                                                </Link>
                                            ))
                                        ) : (
                                            <span className="text-tb-on-surface">
                                                {person.spouse ||
                                                    'Belum dicatat'}
                                            </span>
                                        )}
                                    </dd>
                                </div>
                                {(person.spouses ?? []).map((spouse) => (
                                    <div
                                        key={spouse.id}
                                        className="grid gap-1 rounded-lg border border-tb-outline-variant bg-tb-surface-bright px-3 py-2"
                                    >
                                        <dt className="text-tb-on-surface-variant">
                                            Ayah dari Ibu {spouse.name}
                                        </dt>
                                        <dd className="font-medium text-tb-on-surface">
                                            {spouse.fatherName ??
                                                'Belum dicatat'}
                                            {spouse.fatherMarga
                                                ? ` (${spouse.fatherMarga})`
                                                : ''}
                                        </dd>
                                    </div>
                                ))}
                                <div className="grid gap-1">
                                    <dt className="text-tb-on-surface-variant">
                                        Anak
                                    </dt>
                                    <dd className="leading-relaxed font-medium text-tb-on-surface">
                                        {children.length > 0
                                            ? children.join(', ')
                                            : 'Belum dicatat'}
                                    </dd>
                                </div>
                            </dl>
                        </section>

                        <section
                            id="related-stories"
                            className="rounded-xl border border-tb-outline-variant bg-tb-surface-container/50 p-4"
                        >
                            <div className="mb-3 flex items-center justify-between gap-3">
                                <h3 className="flex items-center gap-2 font-semibold text-tb-on-surface">
                                    <BookOpen className="size-4 text-tb-primary" />
                                    Sejarah/Cerita Terkait
                                </h3>
                                <Button asChild size="sm" variant="outline">
                                    <Link
                                        href={`${peopleRoutes.edit({ person: Number(person.id) }).url}#related-stories`}
                                    >
                                        Tambah Link
                                    </Link>
                                </Button>
                            </div>
                            {person.relatedStories &&
                            person.relatedStories.length > 0 ? (
                                <ol className="grid gap-2">
                                    {person.relatedStories.map(
                                        (story, index) => (
                                            <li
                                                key={`${story.url}-${index}`}
                                                className="rounded-lg border border-tb-outline-variant bg-tb-surface-bright"
                                            >
                                                <a
                                                    href={story.url}
                                                    target="_blank"
                                                    rel="noreferrer noopener"
                                                    className="flex items-center justify-between gap-3 px-3 py-2.5 text-tb-primary transition-colors hover:bg-tb-primary/5 hover:underline"
                                                >
                                                    <span className="min-w-0">
                                                        <span className="mr-2 text-xs font-semibold text-tb-on-surface-variant">
                                                            {index + 1}.
                                                        </span>
                                                        <span className="font-medium">
                                                            {story.title}
                                                        </span>
                                                    </span>
                                                    <ExternalLink className="size-4 shrink-0" />
                                                </a>
                                            </li>
                                        ),
                                    )}
                                </ol>
                            ) : (
                                <p className="text-tb-on-surface-variant">
                                    Belum ada link sejarah atau cerita.
                                </p>
                            )}
                            <p className="mt-3 text-xs text-tb-on-surface-variant">
                                Sumber:{' '}
                                <span className="font-medium text-tb-on-surface">
                                    Link ke website lain
                                </span>
                            </p>
                        </section>
                    </div>

                    <p className="text-xs text-tb-on-surface-variant">
                        Kontributor:{' '}
                        <span className="font-medium text-tb-on-surface">
                            {person.createdBy || 'Belum dicatat'}
                        </span>
                    </p>

                    <DialogFooter className="gap-2 sm:justify-between">
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Tutup
                            </Button>
                        </DialogClose>
                        <div className="flex flex-wrap justify-end gap-2">
                            {person.shareCode && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={copyPersonCode}
                                >
                                    <Copy className="size-4" />
                                    Kopi Kode
                                </Button>
                            )}
                            {canAddBranch && (
                                <Button
                                    type="button"
                                    onClick={() => setBranchDialogOpen(true)}
                                >
                                    <UserPlus className="size-4" /> Tambah
                                    Anggota Ranting
                                </Button>
                            )}
                            <Button asChild variant="outline">
                                <Link
                                    href={peopleRoutes.show(
                                        {
                                            person: Number(person.id),
                                        },
                                        {
                                            query: versionTreeId
                                                ? {
                                                      version_tree:
                                                          versionTreeId,
                                                  }
                                                : {},
                                        },
                                    )}
                                >
                                    Lihat Detail
                                </Link>
                            </Button>
                            {allowBranchEntry &&
                                person.gender !== 'P' && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        router.post(
                                            margaBranchEntries.store({
                                                person: Number(person.id),
                                            }).url,
                                        )
                                    }
                                >
                                    <UserPlus className="size-4" />
                                    Tambah Anggota Ranting
                                </Button>
                            )}
                            <Button asChild>
                                <Link
                                    href={peopleRoutes.edit(
                                        {
                                            person: Number(person.id),
                                        },
                                        {
                                            query: versionTreeId
                                                ? {
                                                      version_tree:
                                                          versionTreeId,
                                                  }
                                                : {},
                                        },
                                    )}
                                >
                                    <Pencil className="size-4" />
                                    Edit
                                </Link>
                            </Button>
                        </div>
                    </DialogFooter>
                </DialogContent>
            )}
            {person && canAddBranch && (
                <FamilyBranchDialog
                    open={branchDialogOpen}
                    onOpenChange={setBranchDialogOpen}
                    familyTree={{
                        id: Number(versionTreeId),
                        requiresApproval: false,
                    }}
                    father={{
                        nodeId: Number(person.treeNodeId),
                        name: person.name,
                    }}
                />
            )}
        </Dialog>
    );
}
