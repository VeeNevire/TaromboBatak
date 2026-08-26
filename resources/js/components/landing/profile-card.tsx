import { X } from 'lucide-react';
import { PersonImage } from '@/components/people/person-image';
import { getGenerationLabel } from '@/data/tarombo-tree';
import type { TaromboPerson } from '@/data/tarombo-tree';

export function ProfileCard({
    person,
    childrenList,
    onClose,
}: {
    person: TaromboPerson | null;
    childrenList: TaromboPerson[];
    onClose?: () => void;
}) {
    if (!person) {
        return (
            <div className="flex w-full max-w-sm flex-col items-center justify-center rounded-2xl border border-tb-outline-variant bg-tb-surface-bright p-6 text-center shadow-xl shadow-tb-surface-container-high/30">
                <p className="text-sm text-tb-on-surface-variant">
                    Pilih anggota pada diagram untuk melihat detail.
                </p>
            </div>
        );
    }

    const detailRows = [
        { label: 'Tahun Lahir', value: person.birthYear ?? '—' },
        { label: 'Marga', value: person.marga },
        {
            label: 'Generasi',
            value: `Gen ${person.generation} (${getGenerationLabel(person.generation)})`,
        },
    ];

    const childNames =
        person.childrenNames ??
        childrenList.map((child) =>
            child.birthYear ? `${child.name} (${child.birthYear})` : child.name,
        );

    return (
        <div className="relative w-full max-w-sm rounded-2xl border border-tb-outline-variant bg-tb-surface-bright p-6 shadow-xl shadow-tb-surface-container-high/30">
            <button
                type="button"
                onClick={onClose}
                className="absolute top-4 right-4 text-tb-outline hover:text-tb-on-surface"
                aria-label="Tutup"
            >
                <X className="h-5 w-5" />
            </button>
            <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center overflow-hidden rounded-full border-2 border-tb-surface bg-tb-surface-container text-sm font-bold text-tb-on-surface-variant">
                <PersonImage
                    src={person.image}
                    name={person.name}
                    className="h-full w-full object-cover"
                />
            </div>
            <div className="mb-6 text-center">
                <p className="text-xs font-semibold tracking-wider text-tb-on-surface-variant uppercase">
                    {person.alias ? person.alias : 'Full Name'}
                </p>
                <h4 className="font-display text-xl font-bold">
                    {person.name}
                </h4>
            </div>
            <div className="space-y-4 text-sm">
                {detailRows.map((row) => (
                    <div key={row.label}>
                        <p className="mb-1 text-xs font-semibold text-tb-on-surface-variant">
                            {row.label}
                        </p>
                        <p>{row.value}</p>
                    </div>
                ))}
                <div>
                    <p className="mb-1 text-xs font-semibold text-tb-on-surface-variant">
                        Anak{' '}
                        {childNames.length > 0 ? `(${childNames.length})` : ''}
                    </p>
                    {childNames.length > 0 ? (
                        childNames.map((child) => (
                            <p key={child} className="mb-0.5">
                                {child}
                            </p>
                        ))
                    ) : (
                        <p className="text-tb-on-surface-variant">
                            Belum ada data anak.
                        </p>
                    )}
                </div>
                <div>
                    <p className="mb-1 text-xs font-semibold text-tb-on-surface-variant">
                        Bio
                    </p>
                    <p className="leading-relaxed text-tb-on-surface-variant">
                        {person.bio ??
                            `${person.name} merupakan bagian dari marga ${person.marga}, keturunan Si Raja Batak.`}
                    </p>
                </div>
            </div>
        </div>
    );
}
