import { Link } from '@inertiajs/react';
import { MapPin } from 'lucide-react';
import { Reveal } from '@/components/landing/reveal';
import kegiatan from '@/routes/kegiatan';

type EventItem = {
    id: number;
    title: string;
    description: string;
    location: string | null;
    month: string;
    day: string;
    is_past?: boolean;
};

export function EventsSection({ events }: { events: EventItem[] }) {
    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h2 className="font-display text-xl font-bold">Event & Kegiatan Komunitas</h2>
                <Link href={kegiatan.index()} className="text-xs font-medium text-tb-outline hover:text-tb-primary">
                    Lihat Semua
                </Link>
            </div>
            <div className="space-y-4">
                {events.length === 0 && (
                    <p className="text-sm text-tb-on-surface-variant">
                        Belum ada event yang akan datang.
                    </p>
                )}
                {events.map((event, index) => (
                    <Reveal key={event.id} delay={index * 0.08}>
                        <Link
                            href={kegiatan.show(event.id)}
                            className={`group flex gap-4 rounded-xl border border-tb-outline-variant bg-tb-surface-bright p-4 transition-colors hover:border-tb-primary ${event.is_past ? 'opacity-60' : ''}`}
                        >
                            <div className="flex w-14 flex-col items-center justify-center rounded-lg bg-tb-surface-container text-tb-primary">
                                <span className="text-xs font-bold uppercase">{event.month}</span>
                                <span className="text-xl font-bold">{event.day}</span>
                            </div>
                            <div className="flex-1">
                                <h3 className="mb-1 text-sm font-bold transition-colors group-hover:text-tb-primary">{event.title}</h3>
                                <p className="mb-2 text-xs text-tb-on-surface-variant">{event.description}</p>
                                <div className="flex items-center justify-between">
                                    <span className="flex items-center gap-1 text-[10px] text-tb-outline">
                                        <MapPin className="h-3 w-3" /> {event.location ?? '-'}
                                    </span>
                                    {event.is_past ? (
                                        <span className="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-600">
                                            Sudah Lewat
                                        </span>
                                    ) : (
                                        <span className="rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-medium text-green-700">
                                            Akan Datang
                                        </span>
                                    )}
                                </div>
                            </div>
                        </Link>
                    </Reveal>
                ))}
            </div>
        </div>
    );
}
