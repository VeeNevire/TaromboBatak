import {
    Building2,
    Calendar,
    MapPin,
    MessageCircle,
    Phone,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

export const komunitasOrganisasi: {
    name: string;
    region: string;
    description: string;
    icon: LucideIcon;
    members: string;
    contact: {
        whatsapp?: string;
        email?: string;
    };
}[] = [
    {
        name: 'Parsadaan Marga Hutasoit Jabodetabek',
        region: 'Jakarta & Sekitarnya',
        description:
            'Organisasi marga Hutasoit yang berdomisili di wilayah Jabodetabek. Aktif mengadakan pertemuan rutin, kegiatan sosial, dan menjaga tali silaturahmi antar anggota marga.',
        icon: Users,
        members: '500+',
        contact: {
            whatsapp: '081234567890',
            email: 'hutasoit.jkt@example.com',
        },
    },
    {
        name: 'Punguan Marga Sitorus Indonesia',
        region: 'Nasional',
        description:
            'Wadah berhimpun seluruh keluarga besar marga Sitorus di Indonesia. Menyelenggarakan kongres nasional, acara tahunan, dan program pemberdayaan anggota.',
        icon: Users,
        members: '2000+',
        contact: {
            whatsapp: '081298765432',
            email: 'sitorus.indonesia@example.com',
        },
    },
    {
        name: 'Punguan Marga Siahaan Nusantara',
        region: 'Nasional',
        description:
            'Organisasi marga Siahaan tingkat nasional yang menghimpun seluruh keturunan Ompu Siahaan. Fokus pada pendidikan, kebudayaan, dan kesejahteraan anggota.',
        icon: Users,
        members: '1500+',
        contact: {
            email: 'siahaan.nasional@example.com',
        },
    },
    {
        name: 'Perkumpulan Marga Simanjuntak',
        region: 'Medan',
        description:
            'Organisasi marga Simanjuntak di Medan yang rutin mengadakan kegiatan sosial, bakti sosial, dan pertemuan keluarga besar setiap tahun.',
        icon: Users,
        members: '800+',
        contact: {
            whatsapp: '081356789012',
        },
    },
    {
        name: 'Parsadaan Hutabarat Indonesia',
        region: 'Nasional',
        description:
            'Perkumpulan keluarga besar marga Hutabarat yang tersebar di seluruh Indonesia. Menyelenggarakan kongres, lomba budaya, dan program beasiswa.',
        icon: Users,
        members: '1200+',
        contact: {
            whatsapp: '081245678901',
            email: 'hutabarat.id@example.com',
        },
    },
    {
        name: 'Ikatan Keluarga Panggabean',
        region: 'Surabaya',
        description:
            'Komunitas marga Panggabean di Jawa Timur yang aktif dalam kegiatan sosial, olahraga, dan pelestarian budaya Batak di wilayah Surabaya.',
        icon: Users,
        members: '400+',
        contact: {
            email: 'panggabean.sby@example.com',
        },
    },
];

export const regionalGroups: {
    name: string;
    region: string;
    description: string;
    icon: LucideIcon;
    members: string;
}[] = [
    {
        name: 'Komunitas Batak Jabodetabek',
        region: 'Jakarta & Sekitarnya',
        description: 'Komunitas lintas marga untuk warga Batak di Jabodetabek.',
        icon: MapPin,
        members: '5000+',
    },
    {
        name: 'Ikatan Keluarga Batak Medan',
        region: 'Medan',
        description: 'Perkumpulan keluarga besar Batak di kota Medan.',
        icon: MapPin,
        members: '8000+',
    },
    {
        name: 'Paguyuban Batak Bandung',
        region: 'Bandung',
        description:
            'Komunitas masyarakat Batak yang tinggal di Bandung dan sekitarnya.',
        icon: MapPin,
        members: '2000+',
    },
    {
        name: 'Komunitas Batak Surabaya',
        region: 'Surabaya',
        description: 'Wadah silaturahmi masyarakat Batak di Surabaya.',
        icon: MapPin,
        members: '1500+',
    },
    {
        name: 'Keluarga Batak Bali',
        region: 'Bali',
        description: 'Komunitas warga Batak yang berdomisili di pulau Bali.',
        icon: MapPin,
        members: '800+',
    },
    {
        name: 'Ikatan Batak Batam',
        region: 'Batam',
        description:
            'Perkumpulan masyarakat Batak di Batam dan Kepulauan Riau.',
        icon: MapPin,
        members: '1200+',
    },
    {
        name: 'Komunitas Batak Makassar',
        region: 'Makassar',
        description: 'Organisasi lintas marga Batak di Sulawesi Selatan.',
        icon: MapPin,
        members: '600+',
    },
    {
        name: 'Paguyuban Batak Papua',
        region: 'Papua',
        description: 'Komunitas warga Batak yang tinggal di Papua.',
        icon: MapPin,
        members: '400+',
    },
];

export const caraBergabung: {
    step: number;
    title: string;
    description: string;
    icon: LucideIcon;
}[] = [
    {
        step: 1,
        title: 'Pilih Komunitas',
        description:
            'Tentukan organisasi atau komunitas yang sesuai dengan wilayah domisili atau marga Anda.',
        icon: MapPin,
    },
    {
        step: 2,
        title: 'Hubungi Kontak Person',
        description:
            'Hubungi pengurus melalui WhatsApp atau email yang tertera untuk mendapatkan informasi lebih lanjut.',
        icon: Phone,
    },
    {
        step: 3,
        title: 'Ikuti Proses Registrasi',
        description:
            'Lengkapi data diri dan dokumen yang diperlukan sesuai prosedur masing-masing organisasi.',
        icon: MessageCircle,
    },
    {
        step: 4,
        title: 'Aktif di Kegiatan',
        description:
            'Ikuti pertemuan rutin, event, dan kegiatan komunitas untuk mempererat tali persaudaraan.',
        icon: Calendar,
    },
];

export const komunitasStats: {
    value: string;
    label: string;
    icon: LucideIcon;
}[] = [
    {
        value: '50+',
        label: 'Organisasi Marga',
        icon: Building2,
    },
    {
        value: '100K+',
        label: 'Anggota Aktif',
        icon: Users,
    },
    {
        value: '30+',
        label: 'Kota di Indonesia',
        icon: MapPin,
    },
    {
        value: '200+',
        label: 'Event per Tahun',
        icon: Calendar,
    },
];
