import { DescendantsTree } from '@/components/people/descendants-tree';
import type { DescendantsAlternativeTree } from '@/components/people/descendants-tree';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { TaromboPerson } from '@/data/tarombo-tree';
import { Search } from 'lucide-react';
import { useEffect, useState } from 'react';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    people: TaromboPerson[];
    alternativeTrees?: DescendantsAlternativeTree[];
    currentId?: string | null;
    onSelect: (person: TaromboPerson) => void;
};

export function PersonTreePickerDialog({
    open,
    onOpenChange,
    people,
    alternativeTrees = [],
    currentId,
    onSelect,
}: Props) {
    const [search, setSearch] = useState('');
    const rootPerson = people.find((person) => !person.parentId) ?? people[0];
    const normalizedSearch = search.trim().toLowerCase();
    const searchResults = normalizedSearch
        ? people
              .filter((person) =>
                  person.name.toLowerCase().includes(normalizedSearch),
              )
              .slice(0, 8)
        : [];

    useEffect(() => {
        if (!open) {
            setSearch('');
        }
    }, [open]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-4xl">
                <DialogHeader>
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <DialogTitle className="font-display text-tb-on-surface">
                                Pohon Silsilah Keturunan
                            </DialogTitle>
                            <DialogDescription>
                                Pilih satu nama pada pohon di bawah ini untuk
                                mengatur identitas Anda di Pohon Tarombo.
                            </DialogDescription>
                        </div>
                        <div className="relative w-44 shrink-0 sm:w-56">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-tb-on-surface-variant" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Cari nama"
                                aria-label="Cari nama anggota"
                                className="h-9 border-tb-primary bg-tb-surface-bright pl-9 text-xs focus:border-tb-primary focus:ring-tb-primary/20"
                            />
                            {normalizedSearch && (
                                <div className="absolute top-full right-0 z-50 mt-1 grid max-h-60 w-64 gap-1 overflow-y-auto rounded-lg border border-tb-outline-variant bg-tb-surface-bright p-1 shadow-lg">
                                    {searchResults.length > 0 ? (
                                        searchResults.map((person) => (
                                            <button
                                                key={person.id}
                                                type="button"
                                                onClick={() => onSelect(person)}
                                                className="flex items-center justify-between gap-3 rounded-md px-3 py-2 text-left text-sm text-tb-on-surface transition-colors hover:bg-tb-surface-container"
                                            >
                                                <span className="min-w-0 truncate font-medium">
                                                    {person.name}
                                                </span>
                                                <span className="shrink-0 text-xs text-tb-on-surface-variant">
                                                    {person.marga ||
                                                        'Marga belum dicatat'}
                                                </span>
                                            </button>
                                        ))
                                    ) : (
                                        <p className="px-3 py-2 text-xs text-tb-on-surface-variant">
                                            Nama tidak ditemukan.
                                        </p>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                </DialogHeader>
                <div className="max-h-[65vh] overflow-auto rounded-xl border border-tb-outline-variant bg-tb-surface-bright p-4">
                    {rootPerson ? (
                        <DescendantsTree
                            people={people}
                            centerId={rootPerson.id}
                            onSelect={(id) => {
                                const person = people.find(
                                    (candidate) => candidate.id === id,
                                );

                                if (person) {
                                    onSelect(person);
                                }
                            }}
                            highlightId={currentId}
                            alternativeTrees={alternativeTrees}
                            nodeIdPrefix="tarombo-picker-node"
                        />
                    ) : (
                        <p className="py-6 text-center text-sm text-tb-on-surface-variant">
                            Belum ada anggota yang dapat dipilih.
                        </p>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
