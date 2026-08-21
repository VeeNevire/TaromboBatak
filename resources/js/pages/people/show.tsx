import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { FamilyTreeHistoryEntry } from '@/components/people/family-tree-history-card';
import { Button } from '@/components/ui/button';
import FamilyForm from '@/pages/people/family-form';
import type { FamilyData } from '@/pages/people/family-form';
import { dashboard } from '@/routes';
import people from '@/routes/people';

type Props = {
    person: FamilyData;
    margas: { id: number; name: string }[];
    nameSuggestions: string[];
    fatherSuggestions: string[];
    familyTrees: FamilyTreeHistoryEntry[];
    versionTrees: FamilyTreeHistoryEntry[];
    canPublish: boolean;
    readOnly?: boolean;
};

export default function PersonShow({
    person,
    margas,
    nameSuggestions,
    fatherSuggestions,
    familyTrees,
    versionTrees,
    canPublish,
    readOnly = false,
}: Props) {
    return (
        <>
            <Head title={`Jejak Keluarga ${person.name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3">
                    <Button
                        asChild
                        variant="ghost"
                        size="sm"
                        className="w-fit text-tb-on-surface-variant"
                    >
                        <Link href={people.index()}>
                            <ArrowLeft className="size-4" /> Kembali ke Data
                            Anggota
                        </Link>
                    </Button>
                    <div>
                        <h1 className="font-display text-2xl font-bold text-tb-on-surface md:text-3xl">
                            Jejak Keluarga {person.name}
                        </h1>
                        <p className="mt-1 text-sm text-tb-on-surface-variant">
                            Ayah, ibu, dan daftar saudara dari {person.name}.
                            Perubahan disimpan sekaligus.
                        </p>
                    </div>
                </div>

                <FamilyForm
                    person={person}
                    margas={margas}
                    nameSuggestions={nameSuggestions}
                    fatherSuggestions={fatherSuggestions}
                    familyTrees={familyTrees}
                    versionTrees={versionTrees}
                    canPublish={canPublish}
                    readOnly={readOnly}
                />
            </div>
        </>
    );
}

PersonShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Data Anggota', href: people.index() },
        { title: 'Jejak Keluarga', href: people.index() },
    ],
};
