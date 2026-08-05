import {
    BookOpen,
    Heart,
    Lightbulb,
    Shapes,
    Sparkles,
    TreePine,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

export const visiMisi = {
    visi: 'Menjadi platform digital terdepan dalam pelestarian silsilah dan budaya Batak untuk generasi masa kini dan mendatang.',
    misi: [
        'Melestarikan warisan leluhur melalui teknologi digital',
        'Menghubungkan keluarga Batak di seluruh dunia',
        'Mendokumentasikan cerita dan tradisi untuk generasi mendatang',
        'Memudahkan penelusuran akar keluarga dan marga',
    ],
};

export const nilaiNilai: {
    title: string;
    description: string;
    icon: LucideIcon;
    iconClass: string;
}[] = [
    {
        title: 'Kehormatan Leluhur',
        description:
            'Menghormati dan menjaga warisan yang diwariskan dari generasi ke generasi dengan penuh tanggung jawab.',
        icon: Heart,
        iconClass: 'bg-rose-100 text-rose-700',
    },
    {
        title: 'Persaudaraan',
        description:
            'Mempererat hubungan keluarga dan komunitas Batak di seluruh dunia melalui teknologi.',
        icon: Users,
        iconClass: 'bg-blue-100 text-blue-700',
    },
    {
        title: 'Pelestarian Budaya',
        description:
            'Menjaga tradisi, bahasa, dan nilai-nilai budaya Batak tetap hidup dan relevan.',
        icon: Sparkles,
        iconClass: 'bg-amber-100 text-amber-700',
    },
    {
        title: 'Inovasi',
        description:
            'Menggunakan teknologi modern untuk menciptakan solusi terbaik dalam pelestarian warisan.',
        icon: Lightbulb,
        iconClass: 'bg-purple-100 text-purple-700',
    },
];

export const impactStats: {
    value: string;
    label: string;
    icon: LucideIcon;
}[] = [
    {
        value: '25.000+',
        label: 'Keluarga Terhubung',
        icon: Users,
    },
    {
        value: '1.000+',
        label: 'Pohon Tarombo',
        icon: TreePine,
    },
    {
        value: '150+',
        label: 'Marga Terdokumentasi',
        icon: Shapes,
    },
    {
        value: '500+',
        label: 'Cerita Tersimpan',
        icon: BookOpen,
    },
];

export const tentangStory = {
    title: 'Mengapa Tarombo Batak Ada?',
    paragraphs: [
        'Di era digital ini, banyak generasi muda Batak yang mulai kehilangan koneksi dengan akar budaya mereka. Silsilah keluarga yang dulunya dijaga dengan cermat mulai terlupakan, cerita leluhur tidak lagi diturunkan, dan tradisi perlahan memudar.',
        'Tarombo Batak hadir untuk menjembatani kesenjangan ini. Kami percaya bahwa dengan teknologi, kita dapat melestarikan warisan leluhur sambil membuatnya relevan dan mudah diakses oleh generasi masa kini.',
        'Platform ini bukan sekadar pohon keluarga digital. Ini adalah ruang di mana cerita hidup, di mana tradisi dijaga, dan di mana keluarga dapat terhubung kembali dengan akar mereka—tidak peduli di mana pun mereka berada.',
    ],
};
