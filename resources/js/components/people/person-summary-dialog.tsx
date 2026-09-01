import { Link } from '@inertiajs/react';
import {
    BookOpen,
    CalendarDays,
    ExternalLink,
    GitBranch,
    MapPin,
    Pencil,
    UserRound,
} from 'lucide-react';
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
}: {
    person: TaromboPerson | null;
    people: TaromboPerson[];
    onClose: () => void;
}) {
    const father = person?.parentId
        ? people.find((candidate) => candidate.id === person.parentId)
        : undefined;
    const children = person
        ? (person.childrenNames ??
          people
              .filter((candidate) => candidate.parentId === person.id)
              .map((child) => child.name))
        : [];

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
                                    Object.values(person.location).some(Boolean) && (
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

                        {person.bio && (
                            <section className="rounded-xl border border-tb-outline-variant bg-tb-surface-container/50 p-4">
                                <h3 className="mb-2 font-semibold text-tb-on-surface">
                                    Biografi
                                </h3>
                                <p className="whitespace-pre-line leading-relaxed text-tb-on-surface-variant">
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
                                    <dd className="text-right font-medium text-tb-on-surface">
                                        {person.spouse || 'Belum dicatat'}
                                    </dd>
                                </div>
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

                        <section id="related-stories" className="rounded-xl border border-tb-outline-variant bg-tb-surface-container/50 p-4">
                                    <div className="mb-3 flex items-center justify-between gap-3">
                                        <h3 className="flex items-center gap-2 font-semibold text-tb-on-surface">
                                        <BookOpen className="size-4 text-tb-primary" />
                                        Sejarah/Cerita Terkait
                                        </h3>
                                        <Button asChild size="sm" variant="outline">
                                            <Link href={`${peopleRoutes.edit({ person: Number(person.id) })}#related-stories`}>
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
                            <Button asChild variant="outline">
                                <Link
                                    href={peopleRoutes.show({
                                        person: Number(person.id),
                                    })}
                                >
                                    Lihat Detail
                                </Link>
                            </Button>
                            <Button asChild>
                                <Link
                                    href={peopleRoutes.edit({
                                        person: Number(person.id),
                                    })}
                                >
                                    <Pencil className="size-4" />
                                    Edit
                                </Link>
                            </Button>
                        </div>
                    </DialogFooter>
                </DialogContent>
            )}
        </Dialog>
    );
}
