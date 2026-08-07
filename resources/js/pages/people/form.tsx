import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import FamilyForm from '@/pages/people/family-form';
import type { FamilyData } from '@/pages/people/family-form';
import { dashboard } from '@/routes';
import people from '@/routes/people';

type Props = {
    person: FamilyData | null;
    margas: { id: number; name: string }[];
    nameSuggestions: string[];
    nomorUsed: { nomor: string; name: string }[];
};

export default function PersonForm({ person, margas, nameSuggestions, nomorUsed }: Props) {
    const isEdit = person !== null;

    return (
        <>
            <Head title={isEdit ? 'Ubah Keluarga' : 'Tambah Keluarga'} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3">
                    <Button asChild variant="ghost" size="sm" className="w-fit text-tb-on-surface-variant">
                        <Link href={people.index()}>
                            <ArrowLeft className="size-4" /> Kembali ke Data Anggota
                        </Link>
                    </Button>
                    <div>
                        <h1 className="font-display text-2xl font-bold text-tb-on-surface md:text-3xl">
                            {isEdit ? 'Ubah Keluarga' : 'Tambah Keluarga'}
                        </h1>
                        <p className="mt-1 text-sm text-tb-on-surface-variant">
                            Isi jejak keluarga: orang tua dan daftar saudara/anak dari ayah yang sama.
                        </p>
                    </div>
                </div>

                <FamilyForm person={person} margas={margas} nameSuggestions={nameSuggestions} nomorUsed={nomorUsed} />
            </div>
        </>
    );
}

PersonForm.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Data Anggota', href: people.index() },
        { title: 'Form Keluarga', href: people.create() },
    ],
};