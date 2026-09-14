import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

type AlternativeVersionDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    submitUrl: string;
    defaultName: string;
};

export function AlternativeVersionDialog({
    open,
    onOpenChange,
    submitUrl,
    defaultName,
}: AlternativeVersionDialogProps) {
    const [name, setName] = useState(defaultName);

    useEffect(() => {
        if (open) {
            setName(defaultName);
        }
    }, [defaultName, open]);

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        router.post(
            submitUrl,
            { alternative_name: name.trim() || defaultName },
            {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Buat Versi Alternatif</DialogTitle>
                    <DialogDescription>
                        Masukkan nama alias untuk membedakan salinan silsilah
                        ini pada Tabel Silsilah milik Akun.
                    </DialogDescription>
                </DialogHeader>
                <form className="grid gap-4" onSubmit={submit}>
                    <div className="grid gap-2">
                        <label
                            htmlFor="alternative-version-name"
                            className="text-sm font-medium text-tb-on-surface"
                        >
                            Nama alias alternatif
                        </label>
                        <Input
                            id="alternative-version-name"
                            value={name}
                            onChange={(event) => setName(event.target.value)}
                            maxLength={120}
                            autoFocus
                        />
                    </div>
                    <div className="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Batal
                        </Button>
                        <Button type="submit" disabled={!name.trim()}>
                            Buat Salinan
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
