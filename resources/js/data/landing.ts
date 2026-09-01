import {
    BookOpen,
    CloudUpload,
    Network,
    Search,
    ShieldCheck,
    TreePine,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { dashboard } from '@/routes';
import budaya from '@/routes/budaya';
import komunitas from '@/routes/komunitas';
import marga from '@/routes/marga';
import tarombo from '@/routes/tarombo';
import tentang from '@/routes/tentang';

export const navLinks = [
    { label: 'Beranda', href: dashboard() },
    { label: 'Tarombo', href: tarombo.view() },
    { label: 'Marga', href: marga.view() },
    { label: 'Budaya', href: budaya.view() },
    { label: 'Komunitas', href: komunitas.view() },
    { label: 'Tentang', href: tentang.view() },
];

export const stats = [
    { value: '25.734+', label: 'Anggota', icon: Users },
    { value: '1.284+', label: 'Tarombo Terdaftar', icon: Network },
    { value: '150+', label: 'Marga', icon: null },
];

export const diagramMenu = [
    { label: 'About & Help', icon: 'help' },
    { label: 'Kamus Tarombo', icon: 'book' },
    { label: 'Jejak Pinompar', icon: 'footprint' },
    { label: 'Unduh Silsilah', icon: 'download' },
];

export const features: {
    title: string;
    description: string;
    icon: LucideIcon;
    iconClass: string;
}[] = [
    {
        title: 'Pohon Tarombo',
        description:
            'Visualisasi silsilah interaktif hingga generasi tak terbatas.',
        icon: TreePine,
        iconClass: 'bg-green-100 text-green-700',
    },
    {
        title: 'Kamus Tarombo',
        description: 'Pahami istilah dan struktur tarombo dengan mudah.',
        icon: BookOpen,
        iconClass: 'bg-orange-100 text-tb-primary',
    },
    {
        title: 'Pencarian Cerdas',
        description: 'Temukan anggota atau marga dengan cepat dan akurat.',
        icon: Search,
        iconClass: 'bg-blue-100 text-blue-700',
    },
    {
        title: 'Privasi Terjaga',
        description:
            'Data keluarga aman dengan kontrol privasi yang fleksibel.',
        icon: ShieldCheck,
        iconClass: 'bg-emerald-100 text-emerald-700',
    },
    {
        title: 'Kolaborasi Keluarga',
        description: 'Bekerja sama melengkapi data silsilah bersama.',
        icon: Users,
        iconClass: 'bg-red-100 text-red-700',
    },
    {
        title: 'Aman & Terbackup',
        description:
            'Data tersimpan aman di cloud dan dapat diunduh kapan saja.',
        icon: CloudUpload,
        iconClass: 'bg-indigo-100 text-indigo-700',
    },
];

export const testimonial = {
    quote: 'Tarombo Batak membantu saya menemukan cabang keluarga yang sudah lama hilang kontaknya. Platform yang keren dan sangat bermanfaat untuk generasi muda seperti saya.',
    name: 'Rafael Simanjuntak',
    since: 'Anggota sejak 2023',
    image: 'https://lh3.googleusercontent.com/aida-public/AB6AXuAU0yZO3D4Yq8g3TJt5q0OjELCadDm06lVfMDxZO-6e8Tr6MW0fidoBQmGsYguSKN4P6fk7f0yOv9aZHq3Fxzcdt2wJde6_vO5zAjUm9kYWE-pcFRNKI7FSBJlRRXFbSCI4iRXYCbRTDpKOfq7jz_coSaY7SGnoAxdnw8LyZIPgknPOjyKXPDWZtbva7bmbBm9oVUTLyskvHbMaJKryPgkF9TVm1HsIKVWp-07RxZWOP6K0tbHRF2g',
};

export const footerNav = {
    navigasi: [
        { label: 'Beranda', href: dashboard() },
        { label: 'Tarombo', href: tarombo.view() },
        { label: 'Marga', href: marga.view() },
        { label: 'Budaya', href: budaya.view() },
        { label: 'Komunitas', href: komunitas.view() },
        { label: 'Tentang', href: tentang.view() },
    ],
    bantuan: [
        'About & Help',
        'Cara Menggunakan',
        'Privasi & Keamanan',
        'Syarat & Ketentuan',
        'Hubungi Kami',
        'FAQ',
    ],
};
