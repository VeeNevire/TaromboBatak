import { router } from '@inertiajs/react';
import { ArrowDown, ArrowUp } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { TaromboPerson } from '@/data/tarombo-tree';
import familyTreeSiblingOrders from '@/routes/family-trees/sibling-order';
import siblingOrders from '@/routes/margas/sibling-order';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    margaId?: number;
    familyTreeId?: number;
    father: { id: number; nodeId?: number; name: string };
    siblings: TaromboPerson[];
};

export function MargaSiblingOrderDialog({
    open,
    onOpenChange,
    margaId,
    familyTreeId,
    father,
    siblings,
}: Props) {
    const [orderedSiblings, setOrderedSiblings] = useState(() =>
        [...siblings].sort(
            (a, b) =>
                (a.birthOrder ?? Number.MAX_SAFE_INTEGER) -
                (b.birthOrder ?? Number.MAX_SAFE_INTEGER),
        ),
    );
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | undefined>();

    const move = (index: number, offset: -1 | 1) => {
        const target = index + offset;

        if (target < 0 || target >= orderedSiblings.length) {
            return;
        }

        setOrderedSiblings((current) => {
            const next = [...current];
            [next[index], next[target]] = [next[target], next[index]];

            return next;
        });
    };

    const save = () => {
        setProcessing(true);
        setError(undefined);

        const request =
            familyTreeId !== undefined
                ? {
                      url: familyTreeSiblingOrders.update({
                          familyTree: familyTreeId,
                      }).url,
                      data: {
                          father_node_id: father.nodeId,
                          node_ids: orderedSiblings.map((person) =>
                              Number(person.treeNodeId),
                          ),
                      },
                  }
                : {
                      url: siblingOrders.update({ marga: Number(margaId) }).url,
                      data: {
                          father_id: father.id,
                          person_ids: orderedSiblings.map((person) =>
                              Number(person.id),
                          ),
                      },
                  };

        router.post(request.url, request.data, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
            onError: (errors) =>
                setError(
                    errors.person_ids ?? errors.node_ids ?? errors.entries,
                ),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85dvh] overflow-y-auto border-tb-outline-variant bg-tb-surface-bright sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Atur Urutan Abang–Adik</DialogTitle>
                    <DialogDescription>
                        Gunakan panah untuk mengubah urutan anak dari{' '}
                        {father.name}.
                    </DialogDescription>
                </DialogHeader>

                <ol className="grid gap-2">
                    {orderedSiblings.map((sibling, index) => (
                        <li
                            key={sibling.id}
                            className="flex items-center gap-2 rounded-lg border border-tb-outline-variant bg-tb-surface-container/40 p-2"
                        >
                            <span className="w-8 shrink-0 text-center text-sm font-semibold text-tb-on-surface-variant">
                                {index + 1}
                            </span>
                            <span className="min-w-0 flex-1 truncate text-sm font-medium text-tb-on-surface">
                                {sibling.name}
                            </span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label={`Naikkan ${sibling.name}`}
                                disabled={index === 0 || processing}
                                onClick={() => move(index, -1)}
                            >
                                <ArrowUp className="size-4" />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label={`Turunkan ${sibling.name}`}
                                disabled={
                                    index === orderedSiblings.length - 1 ||
                                    processing
                                }
                                onClick={() => move(index, 1)}
                            >
                                <ArrowDown className="size-4" />
                            </Button>
                        </li>
                    ))}
                </ol>
                <InputError message={error} />

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={processing}
                        onClick={() => onOpenChange(false)}
                    >
                        Batal
                    </Button>
                    <Button
                        type="button"
                        disabled={processing || orderedSiblings.length < 2}
                        onClick={save}
                    >
                        {processing ? 'Menyimpan…' : 'Simpan Urutan'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
