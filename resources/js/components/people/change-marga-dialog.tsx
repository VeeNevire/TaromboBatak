import { useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import {
    index,
    update,
} from '@/actions/App/Http/Controllers/PersonMargaController';
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

export function ChangeMargaDialog({
    person,
    onClose,
    onSaved,
}: {
    person: TaromboPerson;
    onClose: () => void;
    onSaved: () => void;
}) {
    const [margas, setMargas] = useState<{ id: number; name: string }[]>([]);
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState('');
    const form = useForm({ marga_id: '', include_descendants: false });

    useEffect(() => {
        const controller = new AbortController();
        fetch(index.url({ person: Number(person.id) }), {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error(
                        'Daftar marga tidak dapat dimuat. Tutup dialog dan coba lagi.',
                    );
                }

                const data = await response.json();
                setMargas(data.margas);
                setLoading(false);
            })
            .catch((error: Error) => {
                if (controller.signal.aborted) {
                    return;
                }

                setLoadError(error.message);
                setLoading(false);
            });

        return () => controller.abort();
    }, [person.id]);

    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open && !form.processing) {
                    onClose();
                }
            }}
        >
            <DialogContent className="border-tb-outline-variant bg-tb-surface-bright sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Ganti Marga</DialogTitle>
                    <DialogDescription>
                        Pilih marga baru untuk {person.name}. Marga saat ini:{' '}
                        {person.marga || 'Belum dicatat'}.
                    </DialogDescription>
                </DialogHeader>
                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.patch(update.url({ person: Number(person.id) }), {
                            preserveScroll: true,
                            onSuccess: () => {
                                toast.success('Marga berhasil diganti.');
                                onSaved();
                            },
                        });
                    }}
                >
                    <div className="grid gap-2">
                        <label
                            htmlFor="new-marga"
                            className="text-sm font-medium"
                        >
                            Marga baru
                        </label>
                        <select
                            id="new-marga"
                            required
                            disabled={loading || form.processing || !!loadError}
                            value={form.data.marga_id}
                            onChange={(event) =>
                                form.setData('marga_id', event.target.value)
                            }
                            className="w-full rounded-md border border-tb-outline-variant bg-tb-surface-bright p-2 text-tb-on-surface"
                        >
                            <option value="">
                                {loading ? 'Memuat marga...' : 'Pilih marga'}
                            </option>
                            {margas.map((marga) => (
                                <option key={marga.id} value={marga.id}>
                                    {marga.name}
                                </option>
                            ))}
                        </select>
                        <InputError
                            message={loadError || form.errors.marga_id}
                        />
                    </div>
                    <fieldset className="grid gap-2" disabled={form.processing}>
                        <legend className="mb-2 text-sm font-medium">
                            Ikut ganti marga seluruh ranting turunan?
                        </legend>
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="radio"
                                name="marga-scope"
                                checked={!form.data.include_descendants}
                                onChange={() =>
                                    form.setData('include_descendants', false)
                                }
                            />
                            Tidak, hanya anggota ini
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="radio"
                                name="marga-scope"
                                checked={form.data.include_descendants}
                                onChange={() =>
                                    form.setData('include_descendants', true)
                                }
                            />
                            Ya, anggota ini dan seluruh keturunannya
                        </label>
                        <p className="text-sm text-tb-on-surface-variant">
                            Keturunan mengikuti jalur ayah sampai ranting paling
                            bawah, termasuk anak laki-laki dan perempuan.
                            Pasangan tidak ikut berubah. Perubahan data anggota
                            berlaku di semua silsilah yang memakainya.
                        </p>
                        <InputError message={form.errors.include_descendants} />
                    </fieldset>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={form.processing}
                            onClick={onClose}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            disabled={
                                loading ||
                                !!loadError ||
                                !form.data.marga_id ||
                                form.processing
                            }
                        >
                            {form.processing ? 'Menyimpan...' : 'Simpan Marga'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
