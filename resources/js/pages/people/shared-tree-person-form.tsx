import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, UserPlus } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import familyTrees from '@/routes/family-trees';
import people from '@/routes/people';

type NodeOption = { id: number; name: string; chain: string | null };

type Props = {
    familyTree: { id: number; name: string };
    fatherOptions: NodeOption[];
    motherOptions: NodeOption[];
};

const optionLabel = (option: NodeOption) =>
    option.chain ? `${option.chain} · ${option.name}` : option.name;

export default function SharedTreePersonForm({
    familyTree,
    fatherOptions,
    motherOptions,
}: Props) {
    return (
        <>
            <Head title={`Tambah Anggota - ${familyTree.name}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <Button asChild variant="ghost" size="sm" className="w-fit text-tb-on-surface-variant">
                    <Link href={familyTrees.show(familyTree.id)}>
                        <ArrowLeft className="size-4" /> Kembali ke Silsilah
                    </Link>
                </Button>

                <Card className="mx-auto w-full max-w-3xl border-tb-outline-variant bg-tb-surface-bright">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 font-display text-xl text-tb-on-surface">
                            <UserPlus className="size-5 text-tb-primary" /> Tambah Anggota
                        </CardTitle>
                        <CardDescription>
                            Tambahkan anggota baru ke {familyTree.name}. Data dan struktur anggota yang sudah ada tidak dapat diubah dari akses berbagi.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form {...familyTrees.people.store.form(familyTree.id)} className="grid gap-5">
                            {({ errors, processing }) => (
                                <>
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <div className="grid gap-2 md:col-span-2">
                                            <Label htmlFor="father_node_id">Ayah di dalam silsilah</Label>
                                            <select id="father_node_id" name="father_node_id" required className="h-10 rounded-lg border border-tb-outline-variant bg-tb-surface-bright px-3 text-sm text-tb-on-surface focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20 focus:outline-none">
                                                <option value="">Pilih Ayah...</option>
                                                {fatherOptions.map((option) => <option key={option.id} value={option.id}>{optionLabel(option)}</option>)}
                                            </select>
                                            <InputError message={errors.father_node_id} />
                                        </div>
                                        <div className="grid gap-2 md:col-span-2">
                                            <Label htmlFor="mother_node_id">Ibu di dalam silsilah (opsional)</Label>
                                            <select id="mother_node_id" name="mother_node_id" className="h-10 rounded-lg border border-tb-outline-variant bg-tb-surface-bright px-3 text-sm text-tb-on-surface focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20 focus:outline-none">
                                                <option value="">Belum dipilih</option>
                                                {motherOptions.map((option) => <option key={option.id} value={option.id}>{optionLabel(option)}</option>)}
                                            </select>
                                            <InputError message={errors.mother_node_id} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="name">Nama lengkap</Label>
                                            <Input id="name" name="name" required />
                                            <InputError message={errors.name} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="alias">Alias / gelar</Label>
                                            <Input id="alias" name="alias" />
                                            <InputError message={errors.alias} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="gender">Jenis kelamin</Label>
                                            <select id="gender" name="gender" className="h-10 rounded-lg border border-tb-outline-variant bg-tb-surface-bright px-3 text-sm text-tb-on-surface">
                                                <option value="">Belum dicatat</option>
                                                <option value="L">Laki-laki</option>
                                                <option value="P">Perempuan</option>
                                            </select>
                                            <InputError message={errors.gender} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="birth_order">Urutan lahir</Label>
                                            <Input id="birth_order" name="birth_order" type="number" min="1" placeholder="Otomatis jika kosong" />
                                            <InputError message={errors.birth_order} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="birth_year">Tahun lahir</Label>
                                            <Input id="birth_year" name="birth_year" inputMode="numeric" maxLength={4} />
                                            <InputError message={errors.birth_year} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="death_year">Tahun wafat</Label>
                                            <Input id="death_year" name="death_year" inputMode="numeric" maxLength={4} />
                                            <InputError message={errors.death_year} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="spouse">Nama pasangan</Label>
                                            <Input id="spouse" name="spouse" />
                                            <InputError message={errors.spouse} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="spouse_marga">Marga pasangan</Label>
                                            <Input id="spouse_marga" name="spouse_marga" />
                                            <InputError message={errors.spouse_marga} />
                                        </div>
                                        <div className="grid gap-2 md:col-span-2">
                                            <Label htmlFor="bio">Biografi singkat</Label>
                                            <textarea id="bio" name="bio" rows={4} className="min-h-24 rounded-lg border border-tb-outline-variant bg-tb-surface-bright px-3 py-2 text-sm text-tb-on-surface focus:border-tb-primary focus:ring-2 focus:ring-tb-primary/20 focus:outline-none" />
                                            <InputError message={errors.bio} />
                                        </div>
                                    </div>
                                    <div className="flex justify-end gap-2 border-t border-tb-outline-variant pt-4">
                                        <Button asChild type="button" variant="outline"><Link href={familyTrees.show(familyTree.id)}>Batal</Link></Button>
                                        <Button type="submit" disabled={processing}><UserPlus className="size-4" /> {processing ? 'Menyimpan...' : 'Tambah Anggota'}</Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

SharedTreePersonForm.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Data Anggota', href: people.index() },
        { title: 'Tambah Anggota Berbagi', href: people.index() },
    ],
};
