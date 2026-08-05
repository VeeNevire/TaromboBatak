import {
    Handshake,
    Heart,
    Music,
    Palette,
    Sparkles,
    UserRound,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

export const dalihanNaTolu: {
    title: string;
    description: string;
    icon: LucideIcon;
}[] = [
    {
        title: 'Hula-hula',
        description:
            'Pihak keluarga istri yang sangat dihormati dalam adat Batak. Posisi ini dianggap sebagai pemberi berkat dan keputusan dalam upacara adat. Somba Marhula-hula (hormat kepada hula-hula) menjadi prinsip utama.',
        icon: Handshake,
    },
    {
        title: 'Dongan Sabutuha',
        description:
            'Saudara semarga atau sekutu, yaitu mereka yang memiliki marga sama atau hubungan sejajar. Elek Marboru (kasih kepada dongan tubu) menjadi prinsip dalam menjaga persaudaraan dan solidaritas.',
        icon: UserRound,
    },
    {
        title: 'Boru',
        description:
            'Pihak anak perempuan atau penerima istri yang senantiasa melayani dan memberi dalam upacara adat. Manat Mardongan Tubu (hati-hati dengan boru) menjadi prinsip untuk menjaga harmoni.',
        icon: Users,
    },
];

export const ulosTypes: {
    title: string;
    description: string;
    icon: LucideIcon;
}[] = [
    {
        title: 'Ulos Ragidup',
        description:
            'Ulos kehidupan yang melambangkan harapan akan kehidupan yang panjang dan sejahtera. Diberikan pada acara pernikahan sebagai berkat untuk pengantin baru.',
        icon: Palette,
    },
    {
        title: 'Ulos Ragi Hotang',
        description:
            'Ulos dengan motif bambu yang melambangkan keteguhan dan kekuatan. Sering digunakan dalam upacara adat penting dan perayaan keluarga besar.',
        icon: Palette,
    },
    {
        title: 'Ulos Sibolang',
        description:
            'Ulos berkabung yang digunakan dalam upacara kematian. Melambangkan kesedihan dan penghormatan terakhir kepada yang telah meninggal.',
        icon: Palette,
    },
    {
        title: 'Ulos Bintang Maratur',
        description:
            'Ulos dengan motif bintang tersusun rapi yang melambangkan keindahan dan keharmonisan. Dipakai dalam acara-acara sakral dan perayaan besar.',
        icon: Palette,
    },
];

export const gorgaSymbols: {
    title: string;
    description: string;
    icon: LucideIcon;
}[] = [
    {
        title: 'Singa-singa',
        description:
            'Simbol kekuatan, keberanian, dan perlindungan. Ukiran singa-singa sering dijumpai pada rumah adat dan ornamen tradisional sebagai penangkal roh jahat.',
        icon: Sparkles,
    },
    {
        title: 'Boraspati',
        description:
            'Kadal atau cicak yang melambangkan kesuburan dan kemakmuran. Motif ini diyakini membawa rezeki dan keberkahan bagi penghuni rumah.',
        icon: Sparkles,
    },
    {
        title: 'Desa Na Ualu',
        description:
            'Delapan penjuru mata angin yang melambangkan keseimbangan dan keharmonisan alam semesta. Menggambarkan filosofi hidup yang seimbang.',
        icon: Sparkles,
    },
    {
        title: 'Ipon-ipon',
        description:
            'Motif gigi yang melambangkan kekuatan menggigit atau ketegasan. Sering diukir pada tangga rumah adat sebagai simbol perlindungan.',
        icon: Sparkles,
    },
];

export const upacara: {
    title: string;
    description: string;
    icon: LucideIcon;
}[] = [
    {
        title: 'Martumpol',
        description:
            'Upacara lamaran dan pertunangan dalam adat Batak. Kedua keluarga bertemu untuk membicarakan rencana pernikahan, tukar tanda, dan menyepakati adat istiadat yang akan dijalankan.',
        icon: Heart,
    },
    {
        title: 'Mangokkal Holi',
        description:
            'Upacara mengangkat tulang belulang leluhur dari kuburan lama ke kuburan baru atau monumen keluarga. Dilakukan sebagai penghormatan terakhir dan penyatuan keluarga.',
        icon: Heart,
    },
    {
        title: 'Horja Bius',
        description:
            'Pesta adat besar yang melibatkan seluruh marga dan keluarga besar. Diadakan untuk perayaan besar seperti pernikahan, pembangunan rumah, atau syukuran keluarga.',
        icon: Heart,
    },
    {
        title: 'Saur Matua',
        description:
            'Upacara kematian orang tua yang telah berusia lanjut dan memiliki cucu. Dianggap sebagai kematian yang sempurna dan dirayakan dengan sukacita sebagai penghormatan.',
        icon: Heart,
    },
];

export const budayaStats: {
    value: string;
    label: string;
    icon: LucideIcon;
}[] = [
    {
        value: '3',
        label: 'Pilar Dalihan Na Tolu',
        icon: Users,
    },
    {
        value: '20+',
        label: 'Jenis Ulos Tradisional',
        icon: Palette,
    },
    {
        value: '15+',
        label: 'Simbol Gorga',
        icon: Sparkles,
    },
    {
        value: '10+',
        label: 'Upacara Adat',
        icon: Heart,
    },
];
