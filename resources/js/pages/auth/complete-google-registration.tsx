import { Form, Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { BrandLogo } from '@/components/brand-logo';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import googleRegistration from '@/routes/google/registration';
import { login } from '@/routes';
import regionRoutes from '@/routes/regions';

type Option = { code: string; name: string };
type Region = Option & { regencies: Option[] };
type Props = {
    profile: { name: string; email: string; avatar: string | null };
    margas: { id: number; name: string }[];
    regions: Region[];
};

export default function CompleteGoogleRegistration({ profile, margas, regions }: Props) {
    const [province, setProvince] = useState('');
    const [regency, setRegency] = useState('');
    const [district, setDistrict] = useState('');
    const [village, setVillage] = useState('');
    const [districts, setDistricts] = useState<Option[]>([]);
    const [villages, setVillages] = useState<Option[]>([]);

    useEffect(() => {
        if (!regency) {
            return;
        }

        fetch(regionRoutes.districts.url(regency))
            .then((response) => response.json())
            .then((data: { data: Option[] }) => setDistricts(data.data));
    }, [regency]);

    useEffect(() => {
        if (!district) {
            return;
        }

        fetch(regionRoutes.villages.url(district))
            .then((response) => response.json())
            .then((data: { data: Option[] }) => setVillages(data.data));
    }, [district]);

    const regencies = regions.find((item) => item.code === province)?.regencies ?? [];
    const selectClass = 'w-full rounded-xl border border-tb-outline-variant bg-tb-surface-bright px-4 py-3 text-sm text-tb-on-surface';
    const selectProvince = (value: string) => {
        setProvince(value);
        setRegency('');
        setDistrict('');
        setVillage('');
        setDistricts([]);
        setVillages([]);
    };

    return (
        <>
            <Head title="Lengkapi Pendaftaran" />
            <div className="bg-tb-gorga flex min-h-svh items-center justify-center bg-tb-surface px-4 py-12">
                <div className="w-full max-w-md">
                    <div className="mb-8 text-center">
                        <div className="mx-auto mb-6 flex h-14 w-14 items-center justify-center overflow-hidden rounded-full bg-tb-primary">
                            {profile.avatar ? <img src={profile.avatar} alt="" className="size-full object-cover" /> : <BrandLogo className="size-10" />}
                        </div>
                        <h1 className="font-display text-3xl font-bold text-tb-on-surface">Lengkapi profil Anda</h1>
                        <p className="mt-2 text-tb-on-surface-variant">Satu langkah lagi untuk bergabung dengan Tarombo Batak.</p>
                    </div>

                    <div className="rounded-3xl border border-tb-outline-variant bg-tb-surface-bright p-6 shadow-xl sm:p-8">
                        <Form {...googleRegistration.store.form()} className="flex flex-col gap-5">
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-1.5">
                                        <Label>Nama</Label>
                                        <input value={profile.name} readOnly className={selectClass} />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label>Email Google</Label>
                                        <input value={profile.email} readOnly className={selectClass} />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="marga_id">Marga keluarga</Label>
                                        <select name="marga_id" id="marga_id" className={selectClass} defaultValue="">
                                            <option value="">Pilih marga (opsional)</option>
                                            {margas.map((marga) => <option key={marga.id} value={marga.id}>{marga.name}</option>)}
                                        </select>
                                        <InputError message={errors.marga_id} />
                                    </div>
                                    <RegionSelect name="province_code" label="Provinsi domisili" value={province} options={regions} onChange={selectProvince} error={errors.province_code} />
                                    <RegionSelect name="regency_code" label="Kabupaten/Kota domisili" value={regency} options={regencies} onChange={(value) => {
 setRegency(value); setDistrict(''); setVillage(''); setDistricts([]); setVillages([]); 
}} error={errors.regency_code} disabled={!province} />
                                    <RegionSelect name="district_code" label="Kecamatan domisili" value={district} options={districts} onChange={(value) => {
 setDistrict(value); setVillage(''); setVillages([]); 
}} error={errors.district_code} disabled={!regency} />
                                    <RegionSelect name="village_code" label="Desa/Kelurahan domisili" value={village} options={villages} onChange={setVillage} error={errors.village_code} disabled={!district} />
                                    <Button type="submit" disabled={processing} className="w-full rounded-full bg-tb-primary py-3 text-white hover:bg-tb-primary-light">
                                        {processing ? 'Menyimpan...' : 'Selesaikan pendaftaran'}
                                    </Button>
                                </>
                            )}
                        </Form>
                    </div>
                    <p className="mt-6 text-center text-sm text-tb-on-surface-variant"><Link href={login()} className="text-tb-primary hover:underline">Batalkan dan kembali ke login</Link></p>
                </div>
            </div>
        </>
    );
}

function RegionSelect({ name, label, value, options, onChange, error, disabled = false }: { name: string; label: string; value: string; options: Option[]; onChange: (value: string) => void; error?: string; disabled?: boolean }) {
    return (
        <div className="grid gap-1.5">
            <Label htmlFor={name}>{label}</Label>
            <select name={name} id={name} value={value} onChange={(event) => onChange(event.target.value)} disabled={disabled} required className="w-full rounded-xl border border-tb-outline-variant bg-tb-surface-bright px-4 py-3 text-sm text-tb-on-surface disabled:cursor-not-allowed disabled:opacity-60">
                <option value="">Pilih {label.toLowerCase()}</option>
                {options.map((option) => <option key={option.code} value={option.code}>{option.name}</option>)}
            </select>
            <InputError message={error} />
        </div>
    );
}
