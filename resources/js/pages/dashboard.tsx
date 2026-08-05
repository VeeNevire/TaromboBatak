import { Head, Link, usePage } from '@inertiajs/react';
import { Layers, Plus, Shapes, TreePine, Users } from 'lucide-react';
import { AppAvatar } from '@/components/app-avatar';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';
import people from '@/routes/people';
import tarombo from '@/routes/tarombo';

type DashboardProps = {
    stats: {
        totalPeople: number;
        totalMargas: number;
        totalGenerations: number;
    };
    margaDistribution: {
        name: string;
        color: string | null;
        count: number;
    }[];
    recentPeople: {
        id: number;
        name: string;
        alias: string | null;
        marga: string | null;
        marga_color: string | null;
        birth_year: string | null;
        created_at: string | null;
    }[];
    rootNames: string[];
};

export default function Dashboard({
    stats,
    margaDistribution,
    recentPeople,
    rootNames,
}: DashboardProps) {
    const { auth } = usePage().props;
    const isAdmin = auth.user?.role === 'admin';

    const maxCount = Math.max(1, ...margaDistribution.map((m) => m.count));

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-bold text-tb-on-surface md:text-3xl">
                            Horas, {auth.user?.name?.split(' ')[0]}!
                        </h1>
                        <p className="mt-1 text-sm text-tb-on-surface-variant">
                            Selamat datang di Dashboard Tarombo Batak — kelola silsilah keluarga Anda.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {isAdmin && (
                            <ButtonLink href={people.create()} variant="secondary">
                                <Plus className="size-4" /> Tambah Anggota
                            </ButtonLink>
                        )}
                        <ButtonLink href={tarombo.index()}>
                            <TreePine className="size-4" /> Lihat Pohon
                        </ButtonLink>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <StatCard
                        icon={Users}
                        label="Total Anggota"
                        value={stats.totalPeople}
                        accent="bg-tb-primary"
                    />
                    <StatCard
                        icon={Shapes}
                        label="Total Marga"
                        value={stats.totalMargas}
                        accent="bg-[#2a527c]"
                    />
                    <StatCard
                        icon={Layers}
                        label="Generasi Tercatat"
                        value={stats.totalGenerations}
                        accent="bg-[#3e6b48]"
                    />
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-5">
                    <Card className="lg:col-span-3 border-tb-outline-variant bg-tb-surface-bright">
                        <CardHeader>
                            <CardTitle className="font-display text-lg text-tb-on-surface">
                                Distribusi Anggota per Marga
                            </CardTitle>
                            <CardDescription>
                                Jumlah anggota yang tercatat pada setiap marga.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            {margaDistribution.length === 0 && (
                                <p className="text-sm text-tb-on-surface-variant">
                                    Belum ada data marga.
                                </p>
                            )}
                            {margaDistribution.map((m) => (
                                <div key={m.name} className="flex flex-col gap-1.5">
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="font-medium text-tb-on-surface">{m.name}</span>
                                        <span className="text-tb-on-surface-variant">{m.count} anggota</span>
                                    </div>
                                    <div className="h-2.5 w-full overflow-hidden rounded-full bg-tb-surface-container">
                                        <div
                                            className="h-full rounded-full"
                                            style={{
                                                width: `${(m.count / maxCount) * 100}%`,
                                                backgroundColor: m.color ?? 'var(--color-tb-primary)',
                                            }}
                                        />
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card className="lg:col-span-2 border-tb-outline-variant bg-tb-surface-bright">
                        <CardHeader className="flex-row items-center justify-between space-y-0">
                            <div>
                                <CardTitle className="font-display text-lg text-tb-on-surface">
                                    Anggota Terbaru
                                </CardTitle>
                                <CardDescription>Terakhir ditambahkan ke silsilah.</CardDescription>
                            </div>
                            <Link
                                href={people.index()}
                                className="text-sm font-medium text-tb-primary hover:underline"
                            >
                                Lihat semua
                            </Link>
                        </CardHeader>
                        <CardContent className="flex flex-col divide-y divide-tb-outline-variant">
                            {recentPeople.length === 0 && (
                                <p className="text-sm text-tb-on-surface-variant">
                                    Belum ada anggota.
                                </p>
                            )}
                            {recentPeople.map((person) => {
                                const row = (
                                    <>
                                        <AppAvatar
                                            name={person.name}
                                            color={person.marga_color ?? undefined}
                                        />
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium text-tb-on-surface">
                                                {person.name}
                                            </p>
                                            <p className="truncate text-xs text-tb-on-surface-variant">
                                                {person.marga ?? 'Tanpa marga'}
                                                {person.birth_year ? ` • ${person.birth_year}` : ''}
                                            </p>
                                        </div>
                                    </>
                                );

                                return isAdmin ? (
                                    <Link
                                        key={person.id}
                                        href={people.edit(person.id)}
                                        className="flex items-center gap-3 py-3 transition-colors hover:bg-tb-surface-container/50"
                                    >
                                        {row}
                                    </Link>
                                ) : (
                                    <div
                                        key={person.id}
                                        className="flex items-center gap-3 py-3"
                                    >
                                        {row}
                                    </div>
                                );
                            })}
                        </CardContent>
                    </Card>
                </div>

                {rootNames.length > 0 && (
                    <Card className="border-tb-outline-variant bg-tb-surface-container bg-tb-gorga bg-blend-soft-light">
                        <CardContent className="flex flex-col items-start gap-2 py-6 sm:flex-row sm:items-center sm:gap-4">
                            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-tb-primary text-white">
                                <TreePine className="h-5 w-5" />
                            </div>
                            <div>
                                <p className="font-display text-sm font-semibold text-tb-on-surface">
                                    Akar Silsilah: {rootNames.join(' & ')}
                                </p>
                                <p className="text-xs text-tb-on-surface-variant">
                                    Leluhur utama dari mana semua marga dan anggota bercabang.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};

function StatCard({
    icon: Icon,
    label,
    value,
    accent,
}: {
    icon: typeof Users;
    label: string;
    value: number;
    accent: string;
}) {
    return (
        <Card className="border-tb-outline-variant bg-tb-surface-bright">
            <CardContent className="flex items-center gap-4 py-6">
                <div
                    className={`flex h-12 w-12 items-center justify-center rounded-xl text-white ${accent}`}
                >
                    <Icon className="size-6" />
                </div>
                <div>
                    <p className="font-display text-3xl font-bold text-tb-on-surface">{value}</p>
                    <p className="text-sm text-tb-on-surface-variant">{label}</p>
                </div>
            </CardContent>
        </Card>
    );
}

function ButtonLink({
    href,
    variant,
    children,
}: {
    href: React.ComponentProps<typeof Link>['href'];
    variant?: 'default' | 'secondary';
    children: React.ReactNode;
}) {
    return (
        <Link
            href={href}
            className={
                variant === 'secondary'
                    ? 'inline-flex items-center gap-2 rounded-full border border-tb-outline-variant bg-tb-surface-bright px-4 py-2 text-sm font-medium text-tb-on-surface transition-colors hover:bg-tb-surface-container'
                    : 'inline-flex items-center gap-2 rounded-full bg-tb-primary px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-tb-primary-light'
            }
        >
            {children}
        </Link>
    );
}
