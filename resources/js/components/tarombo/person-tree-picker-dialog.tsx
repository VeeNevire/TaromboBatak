import { DescendantsTree } from '@/components/people/descendants-tree';
import type { DescendantsAlternativeTree } from '@/components/people/descendants-tree';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { TaromboPerson } from '@/data/tarombo-tree';

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
    const rootPerson = people.find((person) => !person.parentId) ?? people[0];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-4xl">
                <DialogHeader>
                    <DialogTitle className="font-display text-tb-on-surface">
                        Pohon Silsilah Keturunan
                    </DialogTitle>
                    <DialogDescription>
                        Pilih satu nama pada pohon di bawah ini untuk mengatur
                        identitas Anda di Pohon Tarombo.
                    </DialogDescription>
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
