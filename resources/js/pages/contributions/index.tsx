import { Head, router, useForm } from '@inertiajs/react';
import {
    BellRing,
    BookOpen,
    CalendarDays,
    Check,
    Plus,
    Trash2,
    UserRoundCog,
    X,
} from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { dashboard } from '@/routes';
import contributions from '@/routes/contributions';
import events from '@/routes/events';
import familyTreeDeletions from '@/routes/family-tree-deletions';
import identityRequestRoutes from '@/routes/identity-requests';
import stories from '@/routes/stories';

type Contribution = {
    id: number;
    status: 'pending' | 'approved' | 'rejected';
    requester: string;
    requester_marga: string | null;
    subject: string;
    matched_father: string;
    matched_father_marga: string | null;
    reviewer: string | null;
    reviewed_at: string | null;
    reason: string | null;
    created_at: string | null;
};

type IdentityApproval = {
    id: number;
    status: 'pending' | 'approved' | 'rejected' | 'cancelled';
    requester: string;
    requester_marga: string | null;
    person: string;
    person_marga: string | null;
    reviewer: string | null;
    reviewed_at: string | null;
    reason: string | null;
    created_at: string | null;
};

type Contributor = {
    id: number;
    name: string;
    email: string;
    role: 'contributor_main' | 'contributor_member';
    marga: string | null;
};

type EventApproval = {
    id: number;
    title: string;
    description: string;
    date: string;
    location: string | null;
    status: 'pending' | 'approved' | 'rejected';
    creator: string | null;
    marga: string | null;
    reviewer: string | null;
    reviewed_at: string | null;
    reason: string | null;
    review_version: number;
    created_at: string | null;
};

type StoryApproval = {
    id: number;
    title: string;
    description: string;
    image: string | null;
    classification: 'umum' | 'marga';
    marga: string | null;
    status: 'pending' | 'approved' | 'rejected';
    creator: string | null;
    reviewer: string | null;
    reviewed_at: string | null;
    reason: string | null;
    review_version: number;
    created_at: string | null;
};

type DeletionRequest = {
    id: number;
    tree: string;
    root: string;
    marga: string | null;
    requester: string;
    status: 'pending' | 'approved' | 'rejected';
    reviewer: string | null;
    reviewed_at: string | null;
    reason: string | null;
    created_at: string | null;
};

type Paginated<T> = {
    data: T[];
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Props = {
    requests: Paginated<Contribution>;
    eventRequests: Paginated<EventApproval>;
    storyRequests: Paginated<StoryApproval>;
    deletionRequests: Paginated<DeletionRequest>;
    identityRequests: Paginated<IdentityApproval>;
    contributors: Contributor[];
    margas: { id: number; name: string }[];
    canManageContributors: boolean;
    activeTab: 'requests' | 'events' | 'stories' | 'deletions';
};

export default function ContributionsIndex({
    requests,
    eventRequests,
    storyRequests,
    deletionRequests,
    identityRequests,
    contributors,
    margas,
    canManageContributors,
    activeTab,
}: Props) {
    const [showContributorForm, setShowContributorForm] = useState(false);
    const [toReject, setToReject] = useState<Contribution | null>(null);
    const [eventToReject, setEventToReject] = useState<EventApproval | null>(
        null,
    );
    const [storyToReject, setStoryToReject] = useState<StoryApproval | null>(
        null,
    );
    const [deletionToReject, setDeletionToReject] =
        useState<DeletionRequest | null>(null);
    const [identityToReject, setIdentityToReject] =
        useState<IdentityApproval | null>(null);
    const rejectForm = useForm({ reason: '' });
    const eventReviewForm = useForm({ reason: '', version: 0 });
    const storyReviewForm = useForm({ reason: '', version: 0 });
    const deletionReviewForm = useForm({ reason: '' });
    const identityReviewForm = useForm({ reason: '' });
    const contributorForm = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        marga_id: '',
        role: 'contributor_member',
    });

    const approve = (request: Contribution) => {
        router.post(
            contributions.approve(request.id).url,
            {},
            { preserveScroll: true },
        );
    };

    const reject = () => {
        if (!toReject) {
            return;
        }

        rejectForm.post(contributions.reject(toReject.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                setToReject(null);
                rejectForm.reset();
            },
        });
    };

    const approveEvent = (event: EventApproval) => {
        router.post(
            events.approve(event.id).url,
            { version: event.review_version },
            { preserveScroll: true },
        );
    };

    const openEventRejection = (event: EventApproval) => {
        eventReviewForm.setData({
            reason: '',
            version: event.review_version,
        });
        setEventToReject(event);
    };

    const rejectEvent = () => {
        if (!eventToReject) {
            return;
        }

        eventReviewForm.post(events.reject(eventToReject.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                setEventToReject(null);
                eventReviewForm.reset();
            },
        });
    };

    const approveStory = (story: StoryApproval) => {
        router.post(
            stories.approve(story.id).url,
            { version: story.review_version },
            { preserveScroll: true },
        );
    };

    const openStoryRejection = (story: StoryApproval) => {
        storyReviewForm.setData({
            reason: '',
            version: story.review_version,
        });
        setStoryToReject(story);
    };

    const rejectStory = () => {
        if (!storyToReject) {
            return;
        }

        storyReviewForm.post(stories.reject(storyToReject.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                setStoryToReject(null);
                storyReviewForm.reset();
            },
        });
    };

    const approveDeletion = (deletion: DeletionRequest) => {
        router.post(
            familyTreeDeletions.approve(deletion.id).url,
            {},
            { preserveScroll: true },
        );
    };

    const openDeletionRejection = (deletion: DeletionRequest) => {
        deletionReviewForm.reset();
        deletionReviewForm.clearErrors();
        setDeletionToReject(deletion);
    };

    const rejectDeletion = () => {
        if (!deletionToReject) {
            return;
        }

        deletionReviewForm.post(
            familyTreeDeletions.reject(deletionToReject.id).url,
            {
                preserveScroll: true,
                onSuccess: () => {
                    setDeletionToReject(null);
                    deletionReviewForm.reset();
                },
            },
        );
    };

    const approveIdentity = (identity: IdentityApproval) => {
        router.post(
            identityRequestRoutes.approve(identity.id).url,
            {},
            { preserveScroll: true },
        );
    };

    const rejectIdentity = () => {
        if (!identityToReject) return;

        identityReviewForm.post(
            identityRequestRoutes.reject(identityToReject.id).url,
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIdentityToReject(null);
                    identityReviewForm.reset();
                },
            },
        );
    };

    const storeContributor = (event: React.FormEvent) => {
        event.preventDefault();
        contributorForm.post(contributions.contributors.store().url, {
            preserveScroll: true,
            onSuccess: () => {
                setShowContributorForm(false);
                contributorForm.reset();
            },
        });
    };

    const content = (
        <div className="flex flex-col gap-4">
            {requests.data.length === 0 ? (
                <Card className="border-dashed border-tb-outline-variant bg-tb-surface-bright">
                    <CardContent className="flex flex-col items-center gap-2 py-12 text-center">
                        <BellRing className="size-8 text-tb-on-surface-variant" />
                        <p className="font-medium text-tb-on-surface">
                            Belum ada pengajuan kontribusi
                        </p>
                        <p className="max-w-md text-sm text-tb-on-surface-variant">
                            Pengajuan muncul ketika User Biasa mencocokkan nama
                            Ayah dengan data yang sudah terdaftar.
                        </p>
                    </CardContent>
                </Card>
            ) : (
                requests.data.map((request) => (
                    <Card
                        key={request.id}
                        className="border-tb-outline-variant bg-tb-surface-bright"
                    >
                        <CardContent className="flex flex-col gap-4 p-4 md:flex-row md:items-center md:justify-between">
                            <div className="min-w-0 space-y-2">
                                <div className="flex flex-wrap items-center gap-2">
                                    <StatusBadge status={request.status} />
                                    <span className="text-xs text-tb-on-surface-variant">
                                        {request.created_at}
                                    </span>
                                </div>
                                <p className="font-medium text-tb-on-surface">
                                    {request.requester} mengajukan{' '}
                                    <strong>{request.matched_father}</strong>{' '}
                                    sebagai Ayah dari{' '}
                                    <strong>{request.subject}</strong>.
                                </p>
                                <p className="text-sm text-tb-on-surface-variant">
                                    Marga:{' '}
                                    {request.matched_father_marga ??
                                        request.requester_marga ??
                                        '-'}
                                    {request.reviewer &&
                                        ` · Ditinjau ${request.reviewer} pada ${request.reviewed_at}`}
                                </p>
                                {request.reason && (
                                    <p className="text-sm text-red-700 dark:text-red-300">
                                        Alasan: {request.reason}
                                    </p>
                                )}
                            </div>
                            {request.status === 'pending' && (
                                <div className="flex shrink-0 gap-2">
                                    <Button
                                        variant="outline"
                                        onClick={() => setToReject(request)}
                                    >
                                        <X className="size-4" /> Tolak
                                    </Button>
                                    <Button
                                        className="bg-tb-primary hover:bg-tb-primary-light"
                                        onClick={() => approve(request)}
                                    >
                                        <Check className="size-4" /> Setujui
                                    </Button>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                ))
            )}
            <Pagination page={requests} />
        </div>
    );

    const identityContent = (
        <div className="flex flex-col gap-4">
            {identityRequests.data.length === 0 ? (
                <Card className="border-dashed border-tb-outline-variant bg-tb-surface-bright">
                    <CardContent className="flex flex-col items-center gap-2 py-12 text-center">
                        <UserRoundCog className="size-8 text-tb-on-surface-variant" />
                        <p className="font-medium text-tb-on-surface">
                            Belum ada log identitas
                        </p>
                    </CardContent>
                </Card>
            ) : (
                identityRequests.data.map((identity) => (
                    <Card
                        key={identity.id}
                        className="border-tb-outline-variant bg-tb-surface-bright"
                    >
                        <CardContent className="flex flex-col gap-4 p-4 md:flex-row md:items-center md:justify-between">
                            <div className="min-w-0 space-y-2">
                                <div className="flex flex-wrap items-center gap-2">
                                    <StatusBadge status={identity.status} />
                                    <span className="text-xs text-tb-on-surface-variant">
                                        {identity.created_at}
                                    </span>
                                </div>
                                <p className="font-medium text-tb-on-surface">
                                    {identity.requester} mengajukan identitas{' '}
                                    <strong>{identity.person}</strong>.
                                </p>
                                <p className="text-sm text-tb-on-surface-variant">
                                    Marga:{' '}
                                    {identity.person_marga ??
                                        identity.requester_marga ??
                                        '-'}
                                    {identity.reviewer &&
                                        ` · Ditinjau ${identity.reviewer} pada ${identity.reviewed_at}`}
                                </p>
                                {identity.reason && (
                                    <p className="text-sm text-red-700 dark:text-red-300">
                                        Alasan: {identity.reason}
                                    </p>
                                )}
                            </div>
                            {identity.status === 'pending' && (
                                <div className="flex shrink-0 gap-2">
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            setIdentityToReject(identity)
                                        }
                                    >
                                        <X className="size-4" /> Tolak
                                    </Button>
                                    <Button
                                        className="bg-tb-primary hover:bg-tb-primary-light"
                                        onClick={() =>
                                            approveIdentity(identity)
                                        }
                                    >
                                        <Check className="size-4" /> Setujui
                                    </Button>
                                </div>
                            )}
                            {identity.status === 'approved' &&
                                canManageContributors && (
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            router.post(
                                                identityRequestRoutes.cancel(
                                                    identity.id,
                                                ).url,
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Batalkan Persetujuan
                                    </Button>
                                )}
                        </CardContent>
                    </Card>
                ))
            )}
            <Pagination page={identityRequests} />
        </div>
    );

    const eventContent = (
        <div className="flex flex-col gap-4">
            {eventRequests.data.length === 0 ? (
                <Card className="border-dashed border-tb-outline-variant bg-tb-surface-bright">
                    <CardContent className="flex flex-col items-center gap-2 py-12 text-center">
                        <CalendarDays className="size-8 text-tb-on-surface-variant" />
                        <p className="font-medium text-tb-on-surface">
                            Belum ada pengajuan event
                        </p>
                        <p className="max-w-md text-sm text-tb-on-surface-variant">
                            Event dari User Biasa dalam marga Anda akan muncul
                            di sini untuk ditinjau.
                        </p>
                    </CardContent>
                </Card>
            ) : (
                eventRequests.data.map((event) => (
                    <Card
                        key={event.id}
                        className="border-tb-outline-variant bg-tb-surface-bright"
                    >
                        <CardContent className="flex flex-col gap-4 p-4 md:flex-row md:items-center md:justify-between">
                            <div className="min-w-0 space-y-2">
                                <div className="flex flex-wrap items-center gap-2">
                                    <StatusBadge status={event.status} />
                                    <span className="text-xs text-tb-on-surface-variant">
                                        {event.created_at}
                                    </span>
                                </div>
                                <div>
                                    <p className="font-medium text-tb-on-surface">
                                        {event.title}
                                    </p>
                                    <p className="line-clamp-2 text-sm text-tb-on-surface-variant">
                                        {event.description}
                                    </p>
                                </div>
                                <p className="text-sm text-tb-on-surface-variant">
                                    Dibuat oleh {event.creator ?? 'User'} ·
                                    Marga {event.marga ?? '-'} · {event.date}
                                    {event.location
                                        ? ` · ${event.location}`
                                        : ''}
                                </p>
                                {event.reviewer && (
                                    <p className="text-xs text-tb-on-surface-variant">
                                        Ditinjau {event.reviewer} pada{' '}
                                        {event.reviewed_at}
                                    </p>
                                )}
                                {event.reason && (
                                    <p className="text-sm text-red-700 dark:text-red-300">
                                        Alasan: {event.reason}
                                    </p>
                                )}
                            </div>
                            {event.status === 'pending' && (
                                <div className="flex shrink-0 gap-2">
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            openEventRejection(event)
                                        }
                                    >
                                        <X className="size-4" /> Tolak
                                    </Button>
                                    <Button
                                        className="bg-tb-primary hover:bg-tb-primary-light"
                                        onClick={() => approveEvent(event)}
                                    >
                                        <Check className="size-4" /> Setujui
                                    </Button>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                ))
            )}
            <Pagination page={eventRequests} />
        </div>
    );

    const storyContent = (
        <div className="flex flex-col gap-4">
            {storyRequests.data.length === 0 ? (
                <Card className="border-dashed border-tb-outline-variant bg-tb-surface-bright">
                    <CardContent className="flex flex-col items-center gap-2 py-12 text-center">
                        <BookOpen className="size-8 text-tb-on-surface-variant" />
                        <p className="font-medium text-tb-on-surface">
                            Belum ada pengajuan cerita
                        </p>
                        <p className="max-w-md text-sm text-tb-on-surface-variant">
                            Cerita Umum dan cerita Marga dalam scope Anda akan
                            muncul di sini untuk ditinjau.
                        </p>
                    </CardContent>
                </Card>
            ) : (
                storyRequests.data.map((story) => (
                    <Card
                        key={story.id}
                        className="border-tb-outline-variant bg-tb-surface-bright"
                    >
                        <CardContent className="flex flex-col gap-4 p-4 md:flex-row md:items-center md:justify-between">
                            <div className="flex min-w-0 gap-3">
                                <div className="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-md bg-tb-surface-container">
                                    {story.image ? (
                                        <img
                                            src={story.image}
                                            alt=""
                                            className="size-full object-cover"
                                        />
                                    ) : (
                                        <BookOpen className="size-5 text-tb-outline" />
                                    )}
                                </div>
                                <div className="min-w-0 space-y-2">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <StatusBadge status={story.status} />
                                        <span className="rounded-full bg-tb-surface-container px-2.5 py-1 text-xs font-medium text-tb-on-surface-variant">
                                            {story.classification === 'umum'
                                                ? 'Umum'
                                                : (story.marga ?? 'Marga')}
                                        </span>
                                        <span className="text-xs text-tb-on-surface-variant">
                                            {story.created_at}
                                        </span>
                                    </div>
                                    <div>
                                        <p className="font-medium text-tb-on-surface">
                                            {story.title}
                                        </p>
                                        <p className="line-clamp-2 text-sm text-tb-on-surface-variant">
                                            {story.description}
                                        </p>
                                    </div>
                                    <p className="text-sm text-tb-on-surface-variant">
                                        Dibuat oleh {story.creator ?? 'User'}
                                    </p>
                                </div>
                            </div>
                            {story.status === 'pending' && (
                                <div className="flex shrink-0 gap-2">
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            openStoryRejection(story)
                                        }
                                    >
                                        <X className="size-4" /> Tolak
                                    </Button>
                                    <Button
                                        className="bg-tb-primary hover:bg-tb-primary-light"
                                        onClick={() => approveStory(story)}
                                    >
                                        <Check className="size-4" /> Setujui
                                    </Button>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                ))
            )}
            <Pagination page={storyRequests} />
        </div>
    );

    const deletionContent = (
        <div className="flex flex-col gap-4">
            {deletionRequests.data.length === 0 ? (
                <Card className="border-dashed border-tb-outline-variant bg-tb-surface-bright">
                    <CardContent className="flex flex-col items-center gap-2 py-12 text-center">
                        <Trash2 className="size-8 text-tb-on-surface-variant" />
                        <p className="font-medium text-tb-on-surface">
                            Belum ada pengajuan penghapusan
                        </p>
                        <p className="max-w-md text-sm text-tb-on-surface-variant">
                            Permintaan penghapusan pohon yang terhubung dengan
                            akun lain akan muncul di sini untuk ditinjau.
                        </p>
                    </CardContent>
                </Card>
            ) : (
                deletionRequests.data.map((deletion) => (
                    <Card
                        key={deletion.id}
                        className="border-tb-outline-variant bg-tb-surface-bright"
                    >
                        <CardContent className="flex flex-col gap-4 p-4 md:flex-row md:items-center md:justify-between">
                            <div className="min-w-0 space-y-2">
                                <div className="flex flex-wrap items-center gap-2">
                                    <StatusBadge status={deletion.status} />
                                    <span className="text-xs text-tb-on-surface-variant">
                                        {deletion.created_at}
                                    </span>
                                </div>
                                <p className="font-medium text-tb-on-surface">
                                    {deletion.requester} mengajukan penghapusan{' '}
                                    <strong>{deletion.tree}</strong>.
                                </p>
                                <p className="text-sm text-tb-on-surface-variant">
                                    Akar: {deletion.root} · Marga:{' '}
                                    {deletion.marga ?? '-'}
                                </p>
                                {deletion.reviewer && (
                                    <p className="text-xs text-tb-on-surface-variant">
                                        Ditinjau {deletion.reviewer} pada{' '}
                                        {deletion.reviewed_at}
                                    </p>
                                )}
                                {deletion.reason && (
                                    <p className="text-sm text-red-700 dark:text-red-300">
                                        Alasan: {deletion.reason}
                                    </p>
                                )}
                            </div>
                            {deletion.status === 'pending' && (
                                <div className="flex shrink-0 gap-2">
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            openDeletionRejection(deletion)
                                        }
                                    >
                                        <X className="size-4" /> Tolak
                                    </Button>
                                    <Button
                                        className="bg-tb-primary hover:bg-tb-primary-light"
                                        onClick={() =>
                                            approveDeletion(deletion)
                                        }
                                    >
                                        <Check className="size-4" /> Setujui
                                    </Button>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                ))
            )}
            <Pagination page={deletionRequests} />
        </div>
    );

    return (
        <>
            <Head title="Kontribusi" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="font-display text-2xl font-bold text-tb-on-surface md:text-3xl">
                        Kontribusi
                    </h1>
                    <p className="mt-1 text-sm text-tb-on-surface-variant">
                        Tinjau pencocokan silsilah, penghapusan pohon, pengajuan
                        event, dan cerita dari User Biasa.
                    </p>
                </div>

                <Tabs defaultValue={activeTab}>
                    <TabsList className="h-auto w-full justify-start overflow-x-auto">
                        <TabsTrigger value="requests">
                            Silsilah ({requests.total})
                        </TabsTrigger>
                        <TabsTrigger value="identity">
                            Identitas ({identityRequests.total})
                        </TabsTrigger>
                        <TabsTrigger value="events">
                            Event ({eventRequests.total})
                        </TabsTrigger>
                        <TabsTrigger value="stories">
                            Cerita ({storyRequests.total})
                        </TabsTrigger>
                        <TabsTrigger value="deletions">
                            Penghapusan ({deletionRequests.total})
                        </TabsTrigger>
                        {canManageContributors && (
                            <TabsTrigger value="contributors">
                                Akun Kontributor ({contributors.length})
                            </TabsTrigger>
                        )}
                    </TabsList>
                    <TabsContent value="requests" className="mt-4">
                        {content}
                    </TabsContent>
                    <TabsContent value="identity" className="mt-4">
                        {identityContent}
                    </TabsContent>
                    <TabsContent value="events" className="mt-4">
                        {eventContent}
                    </TabsContent>
                    <TabsContent value="stories" className="mt-4">
                        {storyContent}
                    </TabsContent>
                    <TabsContent value="deletions" className="mt-4">
                        {deletionContent}
                    </TabsContent>
                    {canManageContributors && (
                        <TabsContent value="contributors" className="mt-4">
                            <Card className="border-tb-outline-variant bg-tb-surface-bright">
                                <CardHeader className="flex flex-row items-center justify-between gap-3">
                                    <CardTitle className="text-lg">
                                        Akun Kontributor
                                    </CardTitle>
                                    <Button
                                        className="bg-tb-primary hover:bg-tb-primary-light"
                                        onClick={() =>
                                            setShowContributorForm(true)
                                        }
                                    >
                                        <Plus className="size-4" /> Tambah
                                    </Button>
                                </CardHeader>
                                <CardContent className="overflow-x-auto p-0">
                                    <table className="w-full min-w-[600px] text-sm">
                                        <thead className="border-y border-tb-outline-variant text-left text-xs text-tb-on-surface-variant">
                                            <tr>
                                                <th className="px-5 py-3">
                                                    Nama
                                                </th>
                                                <th className="px-5 py-3">
                                                    Email
                                                </th>
                                                <th className="px-5 py-3">
                                                    Peran
                                                </th>
                                                <th className="px-5 py-3">
                                                    Marga
                                                </th>
                                                <th className="px-5 py-3 text-right">
                                                    Aksi
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-tb-outline-variant">
                                            {contributors.map((contributor) => (
                                                <tr key={contributor.id}>
                                                    <td className="px-5 py-3 font-medium text-tb-on-surface">
                                                        {contributor.name}
                                                    </td>
                                                    <td className="px-5 py-3 text-tb-on-surface-variant">
                                                        {contributor.email}
                                                    </td>
                                                    <td className="px-5 py-3">
                                                        {contributor.role ===
                                                        'contributor_main'
                                                            ? 'Kontributor Utama'
                                                            : 'Kontributor Anggota'}
                                                    </td>
                                                    <td className="px-5 py-3">
                                                        {contributor.marga ??
                                                            '-'}
                                                    </td>
                                                    <td className="px-5 py-3 text-right">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label={`Hapus ${contributor.name}`}
                                                            onClick={() =>
                                                                window.confirm(
                                                                    `Hapus akun ${contributor.name}?`,
                                                                ) &&
                                                                router.delete(
                                                                    contributions.contributors.destroy(
                                                                        contributor.id,
                                                                    ).url,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 className="size-4 text-red-600" />
                                                        </Button>
                                                    </td>
                                                </tr>
                                            ))}
                                            {contributors.length === 0 && (
                                                <tr>
                                                    <td
                                                        colSpan={5}
                                                        className="px-5 py-10 text-center text-tb-on-surface-variant"
                                                    >
                                                        Belum ada akun
                                                        kontributor.
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </CardContent>
                            </Card>
                        </TabsContent>
                    )}
                </Tabs>
            </div>

            <Dialog
                open={toReject !== null}
                onOpenChange={(open) => !open && setToReject(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Tolak Pengajuan</DialogTitle>
                        <DialogDescription>
                            Relasi Ayah tidak akan diterapkan. Alasan bersifat
                            opsional.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="reason">Alasan</Label>
                        <textarea
                            id="reason"
                            value={rejectForm.data.reason}
                            onChange={(event) =>
                                rejectForm.setData('reason', event.target.value)
                            }
                            className="min-h-24 rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setToReject(null)}
                        >
                            Batal
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={rejectForm.processing}
                            onClick={reject}
                        >
                            Tolak Pengajuan
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={storyToReject !== null}
                onOpenChange={(open) => !open && setStoryToReject(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Tolak Pengajuan Cerita</DialogTitle>
                        <DialogDescription>
                            Cerita <strong>{storyToReject?.title}</strong> tidak
                            akan ditampilkan ke publik.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="story-reason">Alasan (opsional)</Label>
                        <textarea
                            id="story-reason"
                            value={storyReviewForm.data.reason}
                            onChange={(event) =>
                                storyReviewForm.setData(
                                    'reason',
                                    event.target.value,
                                )
                            }
                            maxLength={1000}
                            className="min-h-24 rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        />
                        <InputError message={storyReviewForm.errors.reason} />
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setStoryToReject(null)}
                        >
                            Batal
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={storyReviewForm.processing}
                            onClick={rejectStory}
                        >
                            Tolak Cerita
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={eventToReject !== null}
                onOpenChange={(open) => !open && setEventToReject(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Tolak Pengajuan Event</DialogTitle>
                        <DialogDescription>
                            Event <strong>{eventToReject?.title}</strong> tidak
                            akan ditampilkan ke publik.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="event-reason">Alasan (opsional)</Label>
                        <textarea
                            id="event-reason"
                            value={eventReviewForm.data.reason}
                            onChange={(event) =>
                                eventReviewForm.setData(
                                    'reason',
                                    event.target.value,
                                )
                            }
                            maxLength={1000}
                            className="min-h-24 rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        />
                        <InputError message={eventReviewForm.errors.reason} />
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setEventToReject(null)}
                        >
                            Batal
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={eventReviewForm.processing}
                            onClick={rejectEvent}
                        >
                            Tolak Event
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={deletionToReject !== null}
                onOpenChange={(open) => !open && setDeletionToReject(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Tolak Penghapusan Silsilah</DialogTitle>
                        <DialogDescription>
                            Pohon <strong>{deletionToReject?.tree}</strong> akan
                            tetap tersedia. Alasan penolakan bersifat opsional.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="deletion-reason">
                            Alasan (opsional)
                        </Label>
                        <textarea
                            id="deletion-reason"
                            value={deletionReviewForm.data.reason}
                            onChange={(event) =>
                                deletionReviewForm.setData(
                                    'reason',
                                    event.target.value,
                                )
                            }
                            maxLength={1000}
                            className="min-h-24 rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        />
                        <InputError
                            message={deletionReviewForm.errors.reason}
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeletionToReject(null)}
                        >
                            Batal
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={deletionReviewForm.processing}
                            onClick={rejectDeletion}
                        >
                            Tolak Penghapusan
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={identityToReject !== null}
                onOpenChange={(open) => !open && setIdentityToReject(null)}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Tolak Pengajuan Identitas</DialogTitle>
                        <DialogDescription>
                            Berikan alasan agar pengguna mengetahui hasil
                            verifikasi.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="identity-reason">
                            Alasan (opsional)
                        </Label>
                        <textarea
                            id="identity-reason"
                            value={identityReviewForm.data.reason}
                            onChange={(event) =>
                                identityReviewForm.setData(
                                    'reason',
                                    event.target.value,
                                )
                            }
                            maxLength={1000}
                            className="min-h-24 rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        />
                        <InputError
                            message={identityReviewForm.errors.reason}
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setIdentityToReject(null)}
                        >
                            Batal
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={identityReviewForm.processing}
                            onClick={rejectIdentity}
                        >
                            Tolak Identitas
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={showContributorForm}
                onOpenChange={setShowContributorForm}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <UserRoundCog className="size-5" /> Tambah
                            Kontributor
                        </DialogTitle>
                        <DialogDescription>
                            Akun hanya dapat meninjau pengajuan untuk marga yang
                            dipilih.
                        </DialogDescription>
                    </DialogHeader>
                    <form className="grid gap-4" onSubmit={storeContributor}>
                        <Field
                            label="Nama"
                            name="name"
                            error={contributorForm.errors.name}
                        >
                            <Input
                                id="name"
                                value={contributorForm.data.name}
                                onChange={(e) =>
                                    contributorForm.setData(
                                        'name',
                                        e.target.value,
                                    )
                                }
                                required
                            />
                        </Field>
                        <Field
                            label="Email"
                            name="email"
                            error={contributorForm.errors.email}
                        >
                            <Input
                                id="email"
                                type="email"
                                value={contributorForm.data.email}
                                onChange={(e) =>
                                    contributorForm.setData(
                                        'email',
                                        e.target.value,
                                    )
                                }
                                required
                            />
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                label="Peran"
                                name="role"
                                error={contributorForm.errors.role}
                            >
                                <Select
                                    value={contributorForm.data.role}
                                    onValueChange={(value) =>
                                        contributorForm.setData('role', value)
                                    }
                                >
                                    <SelectTrigger
                                        id="role"
                                        className="w-full border-tb-outline-variant bg-tb-surface-bright"
                                        aria-invalid={
                                            !!contributorForm.errors.role
                                        }
                                    >
                                        <SelectValue placeholder="Pilih peran" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="contributor_main">
                                            Kontributor Utama
                                        </SelectItem>
                                        <SelectItem value="contributor_member">
                                            Kontributor Anggota
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </Field>
                            <Field
                                label="Marga"
                                name="marga_id"
                                error={contributorForm.errors.marga_id}
                            >
                                <Select
                                    value={contributorForm.data.marga_id}
                                    onValueChange={(value) =>
                                        contributorForm.setData(
                                            'marga_id',
                                            value,
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        id="marga_id"
                                        className="w-full border-tb-outline-variant bg-tb-surface-bright"
                                        aria-invalid={
                                            !!contributorForm.errors.marga_id
                                        }
                                    >
                                        <SelectValue placeholder="Pilih marga" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {margas.map((marga) => (
                                            <SelectItem
                                                key={marga.id}
                                                value={String(marga.id)}
                                            >
                                                {marga.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                label="Password"
                                name="password"
                                error={contributorForm.errors.password}
                            >
                                <Input
                                    id="password"
                                    type="password"
                                    value={contributorForm.data.password}
                                    onChange={(e) =>
                                        contributorForm.setData(
                                            'password',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                            </Field>
                            <Field
                                label="Konfirmasi Password"
                                name="password_confirmation"
                            >
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    value={
                                        contributorForm.data
                                            .password_confirmation
                                    }
                                    onChange={(e) =>
                                        contributorForm.setData(
                                            'password_confirmation',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                            </Field>
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setShowContributorForm(false)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                disabled={contributorForm.processing}
                                className="bg-tb-primary hover:bg-tb-primary-light"
                            >
                                Simpan Akun
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

function Field({
    label,
    name,
    error,
    children,
}: {
    label: string;
    name: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            {children}
            {error && <p className="text-xs text-red-600">{error}</p>}
        </div>
    );
}

function StatusBadge({
    status,
}: {
    status: Contribution['status'] | IdentityApproval['status'];
}) {
    const labels = {
        pending: 'Menunggu',
        approved: 'Disetujui',
        rejected: 'Ditolak',
        cancelled: 'Dibatalkan',
    };
    const styles = {
        pending:
            'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
        approved:
            'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
        rejected: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200',
        cancelled:
            'bg-slate-100 text-slate-800 dark:bg-slate-950 dark:text-slate-200',
    };

    return (
        <span
            className={`rounded-full px-2.5 py-1 text-xs font-semibold ${styles[status]}`}
        >
            {labels[status]}
        </span>
    );
}

function Pagination<T>({ page }: { page: Paginated<T> }) {
    if (!page.prev_page_url && !page.next_page_url) {
        return null;
    }

    return (
        <div className="flex items-center justify-between gap-3 text-sm text-tb-on-surface-variant">
            <span>
                {page.from ?? 0}-{page.to ?? 0} dari {page.total}
            </span>
            <div className="flex gap-2">
                <Button
                    variant="outline"
                    disabled={!page.prev_page_url}
                    onClick={() =>
                        page.prev_page_url && router.get(page.prev_page_url)
                    }
                >
                    Sebelumnya
                </Button>
                <Button
                    variant="outline"
                    disabled={!page.next_page_url}
                    onClick={() =>
                        page.next_page_url && router.get(page.next_page_url)
                    }
                >
                    Berikutnya
                </Button>
            </div>
        </div>
    );
}

ContributionsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Kontribusi', href: contributions.index() },
    ],
};
