import { Link, router } from '@inertiajs/react';
import { toJpeg } from 'html-to-image';
import {
    ArrowLeft,
    ExternalLink,
    Images,
    LoaderCircle,
    Maximize2,
    Minimize2,
    Minus,
    Plus,
    Save,
    Search,
    UserSearch,
    X,
} from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import { TaromboDiagram } from '@/components/landing/tarombo-diagram';
import { DescendantsTree } from '@/components/people/descendants-tree';
import type { DescendantsAlternativeTree } from '@/components/people/descendants-tree';
import { PersonTreePickerDialog } from '@/components/tarombo/person-tree-picker-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { buildTaromboPeople } from '@/data/tarombo-tree';
import type {
    MargaInfo,
    TaromboAlternativeTreeRow,
    TaromboPerson,
    TaromboPersonRow,
} from '@/data/tarombo-tree';
import { cn } from '@/lib/utils';
import type { TaromboFamilyTreeOption } from '@/pages/tarombo';
import identityRequests from '@/routes/identity-requests';
import peopleRoutes from '@/routes/people';
import tarombo from '@/routes/tarombo';

type FullscreenView = 'diagram' | 'tree';

export type TaromboIdentity = {
    canSelectAnyPerson: boolean;
    currentPersonId: string | null;
    currentPersonName: string | null;
    request: {
        id: number;
        personId: string;
        personName: string;
        status: 'pending' | 'approved' | 'rejected' | 'cancelled';
        reviewer: string | null;
        reviewedAt: string | null;
        reason: string | null;
    } | null;
};

type Props = {
    people: TaromboPersonRow[];
    margas: MargaInfo[];
    alternativeTrees: TaromboAlternativeTreeRow[];
    identity?: TaromboIdentity;
    fullscreen?: boolean;
    fullscreenView?: FullscreenView;
    initialPersonId?: string;
    familyTreeOptions: TaromboFamilyTreeOption[];
    selectedFamilyTreeId: number | null;
    selectedTreePeople: TaromboPersonRow[] | null;
    margaTree?: {
        margaName: string;
        identityPersonId: string;
        direction: 'upper' | 'lower';
    } | null;
};

const ANCESTOR_DEPTH = 4;

const MY_PERSON_STORAGE_KEY = 'tarombo-my-person-id';

function hasMargaAncestor(people: TaromboPerson[], personId: string): boolean {
    const byId = new Map(people.map((person) => [person.id, person]));
    const visited = new Set<string>();
    let current = byId.get(personId);

    while (current && !visited.has(current.id)) {
        if (
            current.hasMarga ??
            (current.marga !== '' && current.marga !== 'Batak')
        ) {
            return true;
        }

        visited.add(current.id);
        current = current.parentId ? byId.get(current.parentId) : undefined;
    }

    return false;
}

function readStoredMyPersonId(): string | null {
    try {
        return window.localStorage.getItem(MY_PERSON_STORAGE_KEY);
    } catch {
        return null;
    }
}

function removeStoredMyPersonId(): void {
    try {
        window.localStorage.removeItem(MY_PERSON_STORAGE_KEY);
    } catch {
        return;
    }
}

function storeMyPersonId(personId: string): void {
    try {
        window.localStorage.setItem(MY_PERSON_STORAGE_KEY, personId);
    } catch {
        return;
    }
}

function ancestorPath(
    people: TaromboPerson[],
    personId: string,
): TaromboPerson[] {
    const byId = new Map(people.map((person) => [person.id, person]));
    const selected = byId.get(personId);

    if (!selected) {
        return [];
    }

    const path = [selected];
    const visited = new Set<string>([selected.id]);
    let current = selected;

    for (let depth = 0; depth < ANCESTOR_DEPTH; depth += 1) {
        const parent = current.parentId
            ? byId.get(current.parentId)
            : undefined;

        if (!parent || visited.has(parent.id)) {
            break;
        }

        path.unshift(parent);
        visited.add(parent.id);
        current = parent;
    }

    return path;
}

function descendantSubtree(
    people: TaromboPerson[],
    rootId: string,
): TaromboPerson[] {
    const childrenOf = new Map<string, TaromboPerson[]>();

    for (const person of people) {
        if (!person.parentId) {
            continue;
        }

        const children = childrenOf.get(person.parentId) ?? [];
        children.push(person);
        childrenOf.set(person.parentId, children);
    }

    const result: TaromboPerson[] = [];
    const queue = [rootId];
    const visited = new Set<string>();

    while (queue.length > 0) {
        const id = queue.shift();

        if (!id || visited.has(id)) {
            continue;
        }

        const person = people.find((item) => item.id === id);

        if (!person) {
            continue;
        }

        visited.add(id);
        result.push(person);
        queue.push(...(childrenOf.get(id) ?? []).map((child) => child.id));
    }

    return result;
}

export function TaromboExplorer({
    people: rows,
    margas,
    alternativeTrees,
    identity,
    fullscreen = false,
    fullscreenView = 'diagram',
    initialPersonId,
    familyTreeOptions,
    selectedFamilyTreeId,
    selectedTreePeople,
    margaTree = null,
}: Props) {
    const people = buildTaromboPeople(rows);
    const selectedFamilyTreePeople = buildTaromboPeople(
        selectedTreePeople ?? rows,
    );
    const margaIdentity = margaTree
        ? selectedFamilyTreePeople.find(
              (person) => person.id === margaTree.identityPersonId,
          )
        : undefined;
    const margaLineagePath = useMemo(() => {
        if (!margaTree || !margaIdentity) {
            return [] as TaromboPerson[];
        }

        const byId = new Map(
            selectedFamilyTreePeople.map((person) => [person.id, person]),
        );
        const path: TaromboPerson[] = [];
        const visited = new Set<string>();
        let current: TaromboPerson | undefined = margaIdentity;

        while (current && !visited.has(current.id)) {
            path.unshift(current);
            visited.add(current.id);
            current = current.parentId ? byId.get(current.parentId) : undefined;
        }

        return path;
    }, [margaIdentity, margaTree, selectedFamilyTreePeople]);
    const margaTreePeople = useMemo(() => {
        if (!margaTree || !margaIdentity) {
            return selectedFamilyTreePeople;
        }

        if (margaTree.direction === 'lower') {
            return descendantSubtree(
                selectedFamilyTreePeople,
                margaIdentity.id,
            ).filter(
                (person) =>
                    person.id === margaIdentity.id ||
                    person.gender === 'L' ||
                    !person.gender,
            );
        }

        const lineageIds = new Set(margaLineagePath.map((person) => person.id));
        const siblingIds = new Set<string>();

        for (const person of margaLineagePath) {
            for (const sibling of selectedFamilyTreePeople) {
                if (
                    sibling.parentId === person.parentId &&
                    !lineageIds.has(sibling.id)
                ) {
                    siblingIds.add(sibling.id);
                }
            }
        }

        return [
            ...margaLineagePath,
            ...selectedFamilyTreePeople.filter((person) =>
                siblingIds.has(person.id),
            ),
        ];
    }, [margaIdentity, margaLineagePath, margaTree, selectedFamilyTreePeople]);
    const margaDetachedRoots = useMemo(() => {
        if (!margaTree || margaTree.direction !== 'lower' || !margaIdentity) {
            return [] as TaromboPerson[];
        }

        const connectedIds = new Set(
            descendantSubtree(selectedFamilyTreePeople, margaIdentity.id).map(
                (person) => person.id,
            ),
        );

        return selectedFamilyTreePeople.filter(
            (person) =>
                person.marga === margaTree.margaName &&
                !connectedIds.has(person.id) &&
                !person.parentId,
        );
    }, [margaIdentity, margaTree, selectedFamilyTreePeople]);
    const margaDetachedPeople = useMemo(
        () =>
            margaDetachedRoots.flatMap((root) =>
                descendantSubtree(selectedFamilyTreePeople, root.id),
            ),
        [margaDetachedRoots, selectedFamilyTreePeople],
    );
    const rootPerson = people.find((p) => !p.parentId) ?? people[0];
    const connectedPeople = people.filter((person) =>
        hasMargaAncestor(people, person.id),
    );
    const eligiblePeople = identity?.canSelectAnyPerson
        ? connectedPeople
        : connectedPeople.filter(
              (person) => person.gender === 'L' || !person.gender,
          );
    const requestedPersonId = identity?.request?.personId ?? null;
    const requestedPerson = requestedPersonId
        ? people.find((person) => person.id === requestedPersonId)
        : undefined;
    const selectablePeople =
        requestedPerson &&
        !eligiblePeople.some((person) => person.id === requestedPerson.id)
            ? [...eligiblePeople, requestedPerson]
            : eligiblePeople;
    const storedMyId = readStoredMyPersonId();
    const initialMyId = identity
        ? (identity.currentPersonId ??
          (identity.request?.status === 'pending'
              ? identity.request.personId
              : null))
        : storedMyId !== null &&
            selectablePeople.some((person) => person.id === storedMyId)
          ? storedMyId
          : null;
    const initialRequestedPersonId = initialPersonId
        ? people.find((person) => person.id === initialPersonId)?.id
        : undefined;
    const initialFocusId = initialRequestedPersonId ?? initialMyId;
    const [myId, setMyId] = useState<string | null>(initialMyId);
    const [pickerOpen, setPickerOpen] = useState(false);
    const [selectedId, setSelectedId] = useState<string | null>(initialFocusId);
    const [search, setSearch] = useState('');
    const [searchOpen, setSearchOpen] = useState(false);
    const [history, setHistory] = useState<string[]>([]);
    const [expanded, setExpanded] = useState<'diagram' | 'tree' | null>(null);
    const [treeZoom, setTreeZoom] = useState(1);
    const [showFemaleLineage, setShowFemaleLineage] = useState(false);
    const [savingSnapshot, setSavingSnapshot] = useState(false);
    const [snapshotMode, setSnapshotMode] = useState(false);
    const snapshotRef = useRef<HTMLDivElement>(null);
    const [ancestorFocusId, setAncestorFocusId] = useState<string | null>(
        initialFocusId,
    );

    const clampZoom = (value: number) =>
        Math.round(Math.min(2, Math.max(0.5, value)) * 100) / 100;

    const treeZoomControls = (
        <div
            className={cn(
                'flex flex-col overflow-hidden rounded-lg border border-tb-outline-variant bg-tb-surface-bright/95 shadow-md backdrop-blur',
                snapshotMode && 'invisible',
            )}
        >
            <button
                type="button"
                onClick={() => setTreeZoom((z) => clampZoom(z + 0.25))}
                aria-label="Perbesar pohon"
                title="Perbesar pohon"
                className="inline-flex h-9 w-9 items-center justify-center text-tb-on-surface transition-colors hover:bg-tb-surface-container"
            >
                <Plus className="size-4" />
            </button>
            <span className="flex h-6 w-9 items-center justify-center border-y border-tb-outline-variant text-[11px] font-medium text-tb-on-surface-variant">
                {Math.round(treeZoom * 100)}%
            </span>
            <button
                type="button"
                onClick={() => setTreeZoom((z) => clampZoom(z - 0.25))}
                aria-label="Perkecil pohon"
                title="Perkecil pohon"
                className="inline-flex h-9 w-9 items-center justify-center text-tb-on-surface transition-colors hover:bg-tb-surface-container"
            >
                <Minus className="size-4" />
            </button>
        </div>
    );

    const femaleLineageToggle = (
        <label
            className={cn(
                'flex cursor-pointer items-center justify-end gap-2 text-xs font-semibold text-emerald-700 select-none dark:text-emerald-300',
                snapshotMode && 'invisible',
            )}
        >
            <input
                type="checkbox"
                checked={showFemaleLineage}
                onChange={(event) => setShowFemaleLineage(event.target.checked)}
                className="size-4 rounded border-emerald-600 text-emerald-600 accent-emerald-600 focus:ring-2 focus:ring-emerald-500/30"
            />
            Silsilah Perempuan Ditampilkan
        </label>
    );

    const descendantAlternativeTrees: DescendantsAlternativeTree[] =
        alternativeTrees.map((tree) => ({
            id: tree.id,
            name: tree.name,
            rootId: tree.rootPersonId,
            people: buildTaromboPeople(tree.people).filter(
                (person) =>
                    showFemaleLineage ||
                    person.gender === 'L' ||
                    !person.gender,
            ),
        }));
    const [centerPersonId, setCenterPersonId] = useState<string>(
        initialFocusId ?? rootPerson?.id ?? '',
    );
    const normalizedSearch = search.trim().toLowerCase();
    const searchResults = normalizedSearch
        ? people
              .filter((person) =>
                  person.name.toLowerCase().includes(normalizedSearch),
              )
              .sort((a, b) => {
                  const aStarts = a.name
                      .toLowerCase()
                      .startsWith(normalizedSearch);
                  const bStarts = b.name
                      .toLowerCase()
                      .startsWith(normalizedSearch);

                  if (aStarts !== bStarts) {
                      return aStarts ? -1 : 1;
                  }

                  return a.name.localeCompare(b.name, 'id');
              })
              .slice(0, 10)
        : [];
    const verticalPeople = showFemaleLineage
        ? selectedFamilyTreePeople
        : selectedFamilyTreePeople.filter(
              (person) => person.gender === 'L' || !person.gender,
          );
    const ancestorPeople = ancestorFocusId
        ? ancestorPath(verticalPeople, ancestorFocusId)
        : [];
    const treePeople =
        ancestorPeople.length > 0
            ? descendantSubtree(verticalPeople, ancestorPeople[0].id)
            : verticalPeople;
    const lineagePath = ancestorPeople.map((person) => person.id);
    const treeCenterPerson =
        verticalPeople.find(
            (person) => person.id === (ancestorPeople[0]?.id ?? centerPersonId),
        ) ??
        verticalPeople.find((person) => !person.parentId) ??
        verticalPeople[0];
    const treeCenterId = treeCenterPerson?.id ?? '';
    const renderedTreePeople = margaTree
        ? [...margaTreePeople, ...margaDetachedPeople]
        : treePeople;
    const renderedTreeCenterId = margaTree
        ? (margaTreePeople[0]?.id ?? '')
        : treeCenterId;
    const renderedHighlightId = margaTree ? margaIdentity?.id : selectedId;
    const ancestorFocusPerson =
        ancestorFocusId && ancestorPeople.length > 0
            ? verticalPeople.find((person) => person.id === ancestorFocusId)
            : undefined;
    const treeHasChildren = treePeople.some(
        (person) => person.parentId === treeCenterId,
    );
    const myPerson =
        myId !== null ? people.find((person) => person.id === myId) : undefined;

    const searchSelect = (person: TaromboPerson) => {
        if (person.id !== centerPersonId) {
            setHistory((prev) => [...prev, centerPersonId]);
        }

        setSelectedId(person.id);
        setCenterPersonId(person.id);
        setAncestorFocusId(person.id);
        setSearch(person.name);
        setSearchOpen(false);
    };

    const handleIdentitySelect = (person: TaromboPerson) => {
        router.post(
            identityRequests.store().url,
            { person_id: Number(person.id) },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setPickerOpen(false);
                    setMyId(person.id);
                    storeMyPersonId(person.id);

                    if (person.id !== centerPersonId) {
                        setHistory((prev) => [...prev, centerPersonId]);
                    }

                    setSelectedId(person.id);
                    setCenterPersonId(person.id);
                    setAncestorFocusId(person.id);
                },
            },
        );
    };

    const handleIdentityReset = () => {
        removeStoredMyPersonId();
        setMyId(null);
    };

    const handleBack = () => {
        if (history.length === 0) {
            return;
        }

        const prev = history[history.length - 1];
        setHistory((prevHistory) => prevHistory.slice(0, -1));
        setSelectedId(prev === rootPerson?.id ? null : prev);
        setCenterPersonId(prev);
        setAncestorFocusId(null);
        setSearch('');
    };

    const handlePersonSelect = (id: string) => {
        setAncestorFocusId(id);

        if (id === centerPersonId) {
            handleBack();

            return;
        }

        setHistory((prev) => [...prev, centerPersonId]);
        setSelectedId(id);
        setCenterPersonId(id);
    };

    const handleDiagramSelect = (person: TaromboPerson) =>
        handlePersonSelect(person.id);

    const handleSaveSnapshot = async () => {
        const snapshotNode = snapshotRef.current;

        if (!snapshotNode || savingSnapshot) {
            return;
        }

        setSavingSnapshot(true);
        setSnapshotMode(true);

        try {
            await new Promise<void>((resolve) =>
                requestAnimationFrame(() =>
                    requestAnimationFrame(() => resolve()),
                ),
            );
            await document.fonts.ready;

            const dataUrl = await toJpeg(snapshotNode, {
                quality: 0.92,
                pixelRatio: Math.min(2, window.devicePixelRatio || 1),
                backgroundColor:
                    window.getComputedStyle(snapshotNode).backgroundColor,
                cacheBust: true,
            });
            const image = await fetch(dataUrl).then((response) =>
                response.blob(),
            );
            const file = new File(
                [image],
                `pohon-tarombo-${fullscreenView}-${Date.now()}.jpg`,
                { type: 'image/jpeg' },
            );

            router.post(
                tarombo.snapshots.store(),
                {
                    image: file,
                    view: fullscreenView,
                    center_person_id: Number(centerPersonId),
                },
                {
                    forceFormData: true,
                    preserveScroll: true,
                    onError: () => toast.error('Gambar pohon gagal disimpan.'),
                    onFinish: () => setSavingSnapshot(false),
                },
            );
        } catch {
            setSavingSnapshot(false);
            toast.error(
                'Tampilan pohon gagal dibuat menjadi gambar. Coba kembali.',
            );
        } finally {
            setSnapshotMode(false);
        }
    };

    const backButton = (
        <Link
            href={tarombo.index()}
            aria-label="Kembali ke Pohon Tarombo"
            title="Kembali"
            className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-tb-outline-variant bg-tb-surface-bright/95 text-tb-on-surface shadow-md backdrop-blur transition-colors hover:bg-tb-surface-container"
        >
            <ArrowLeft className="size-4" />
        </Link>
    );

    const expandButton = (card: 'diagram' | 'tree', isExpanded: boolean) => (
        <button
            type="button"
            onClick={() => setExpanded(isExpanded ? null : card)}
            aria-label={isExpanded ? 'Kecilkan tampilan' : 'Perbesar tampilan'}
            title={isExpanded ? 'Kecilkan' : 'Perbesar'}
            className="absolute top-3 right-3 z-10 inline-flex h-8 w-8 items-center justify-center rounded-full border border-tb-outline-variant bg-tb-surface-bright/95 text-tb-on-surface shadow-md backdrop-blur transition-colors hover:bg-tb-surface-container"
        >
            {isExpanded ? (
                <Minimize2 className="size-4" />
            ) : (
                <Maximize2 className="size-4" />
            )}
        </button>
    );

    const newTabButton = (view: FullscreenView) => (
        <button
            type="button"
            onClick={() =>
                window.open(
                    tarombo.fullscreen(
                        { view },
                        {
                            query: {
                                person: centerPersonId,
                                family_tree: selectedFamilyTreeId ?? undefined,
                            },
                        },
                    ).url,
                    '_blank',
                    'noopener',
                )
            }
            aria-label="Buka di tab baru"
            title="Buka di tab baru"
            className="absolute top-3 right-14 z-10 inline-flex h-8 w-8 items-center justify-center rounded-full border border-tb-outline-variant bg-tb-surface-bright/95 text-tb-on-surface shadow-md backdrop-blur transition-colors hover:bg-tb-surface-container"
        >
            <ExternalLink className="size-4" />
        </button>
    );

    const accountFamilyTrees = familyTreeOptions.filter(
        (tree) => tree.group === 'account',
    );
    const approvedMargaTrees = familyTreeOptions.filter(
        (tree) => tree.group === 'marga',
    );
    const familyTreeSelector = (
        <div
            className={cn(
                'flex min-w-0 items-center justify-end gap-2',
                snapshotMode && 'invisible',
            )}
        >
            <span className="shrink-0 text-xs font-semibold text-emerald-700 dark:text-emerald-300">
                Silsilah:
            </span>
            <Select
                value={
                    selectedFamilyTreeId === null
                        ? 'default'
                        : String(selectedFamilyTreeId)
                }
                onValueChange={(value) => {
                    const familyTreeId =
                        value === 'default' ? undefined : Number(value);
                    const destination = fullscreen
                        ? tarombo.fullscreen(
                              { view: fullscreenView },
                              {
                                  query: {
                                      person: centerPersonId,
                                      family_tree: familyTreeId,
                                  },
                              },
                          )
                        : tarombo.index({
                              query: { family_tree: familyTreeId },
                          });

                    router.get(destination.url, {}, { preserveScroll: true });
                }}
            >
                <SelectTrigger className="h-8 w-56 border-emerald-300 bg-tb-surface-bright text-xs focus:ring-emerald-500/30">
                    <SelectValue placeholder="Pilih silsilah" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="default">
                        Pohon Tarombo Default
                    </SelectItem>
                    {accountFamilyTrees.length > 0 && (
                        <SelectGroup>
                            <SelectLabel>Silsilah Milik Akun</SelectLabel>
                            {accountFamilyTrees.map((tree) => (
                                <SelectItem
                                    key={tree.id}
                                    value={String(tree.id)}
                                >
                                    {tree.name}
                                </SelectItem>
                            ))}
                        </SelectGroup>
                    )}
                    {approvedMargaTrees.length > 0 && (
                        <SelectGroup>
                            <SelectLabel>Silsilah Marga Disetujui</SelectLabel>
                            {approvedMargaTrees.map((tree) => (
                                <SelectItem
                                    key={tree.id}
                                    value={String(tree.id)}
                                >
                                    {tree.name}
                                </SelectItem>
                            ))}
                        </SelectGroup>
                    )}
                </SelectContent>
            </Select>
        </div>
    );

    const renderDiagramCard = (isExpanded: boolean) => (
        <div
            ref={fullscreen ? snapshotRef : undefined}
            className={cn(
                'relative overflow-hidden rounded-2xl border border-tb-outline-variant bg-tb-surface-bright',
                fullscreen && 'flex min-h-0 flex-col',
            )}
        >
            {!fullscreen && expandButton('diagram', isExpanded)}
            {!fullscreen && newTabButton('diagram')}
            <div
                className={cn(
                    '[scrollbar-gutter:stable] overflow-y-scroll p-4',
                    fullscreen ? 'min-h-0 flex-1' : 'max-h-[600px]',
                    !fullscreen && isExpanded && 'max-h-[70vh]',
                )}
            >
                <TaromboDiagram
                    onSelect={handleDiagramSelect}
                    onPaneClick={() => setSelectedId(null)}
                    onBack={handleBack}
                    canGoBack={history.length > 0}
                    selectedId={selectedId ?? undefined}
                    centerPersonId={centerPersonId}
                    people={people}
                    margas={margas}
                    context="descendants"
                    allowPan={fullscreen}
                    showScrollbars={fullscreen}
                    initialScrollable={fullscreen}
                />
            </div>
        </div>
    );

    const noChildrenNotice = (
        <div className="flex flex-col items-center gap-3 pb-4">
            <p className="text-center text-sm text-tb-on-surface-variant">
                Belum memiliki keturunan tercatat. Klik node lagi untuk kembali.
            </p>
            {treeCenterPerson && (
                <Button
                    asChild
                    size="sm"
                    variant="outline"
                    className="border-tb-outline-variant bg-tb-surface-bright text-tb-on-surface hover:bg-tb-surface-container hover:text-tb-on-surface"
                >
                    <Link
                        href={peopleRoutes.show({
                            person: Number(treeCenterPerson.id),
                        })}
                    >
                        Update Silsilah
                    </Link>
                </Button>
            )}
        </div>
    );

    const renderTreeCard = (isExpanded: boolean) => (
        <div
            ref={fullscreen ? snapshotRef : undefined}
            className={cn(
                'relative overflow-hidden rounded-2xl border border-tb-outline-variant bg-tb-surface-bright',
                fullscreen && 'flex min-h-0 flex-col',
            )}
        >
            {!fullscreen && expandButton('tree', isExpanded)}
            {!fullscreen && newTabButton('tree')}
            <div
                className={cn(
                    '[scrollbar-gutter:stable] overflow-auto p-4',
                    fullscreen ? 'min-h-0 flex-1' : 'max-h-[600px]',
                    !fullscreen && isExpanded && 'max-h-[70vh]',
                )}
            >
                <div className="relative mb-4 border-b border-tb-outline-variant pb-3">
                    {fullscreen ? (
                        <Link
                            href={tarombo.index()}
                            className={cn(
                                'absolute top-0 left-0 inline-flex w-fit items-center gap-1.5 rounded-lg border border-tb-outline-variant bg-tb-surface-bright px-3 py-2 text-xs font-semibold text-tb-on-surface transition-colors hover:bg-tb-surface-container',
                                snapshotMode && 'invisible',
                            )}
                        >
                            <ArrowLeft className="size-4" /> Kembali
                        </Link>
                    ) : (
                        <span />
                    )}
                    <div className="min-w-0 px-20 text-center">
                        <h3 className="font-display text-lg font-bold text-tb-on-surface">
                            {margaTree
                                ? `Pohon Silsilah ${margaTree.direction === 'upper' ? 'Atas' : 'Bawah'}`
                                : 'Silsilah Keturunan'}
                        </h3>
                        <p className="mt-1 max-w-64 truncate text-xs text-tb-on-surface-variant">
                            {margaTree
                                ? `${margaTree.direction === 'upper' ? 'Si Raja Batak sampai' : 'Keturunan dari'} ${margaIdentity?.name ?? margaTree.margaName}`
                                : ancestorFocusPerson
                                  ? `Jalur ${ANCESTOR_DEPTH} tingkat leluhur dari ${ancestorFocusPerson.name}`
                                  : `Pohon vertikal dari ${treeCenterPerson?.name ?? 'Leluhur Utama'}`}
                        </p>
                    </div>
                    <div className="mt-3 flex flex-wrap items-center justify-center gap-3">
                        {!margaTree && familyTreeSelector}
                        {!margaTree && femaleLineageToggle}
                    </div>
                </div>
                <div style={{ zoom: treeZoom }}>
                    <DescendantsTree
                        key={`${renderedTreeCenterId}-${margaTree?.direction ?? ancestorFocusId ?? 'branch'}-${showFemaleLineage ? 'with-female' : 'male-only'}`}
                        people={renderedTreePeople}
                        centerId={renderedTreeCenterId}
                        onSelect={margaTree ? undefined : handlePersonSelect}
                        highlightId={renderedHighlightId}
                        editNodes={!margaTree}
                        selectOnClick={!margaTree}
                        showProfileOnName
                        readOnly={Boolean(margaTree)}
                        alternativeTrees={descendantAlternativeTrees}
                        lineagePath={
                            margaTree?.direction === 'upper'
                                ? margaLineagePath.map((person) => person.id)
                                : lineagePath
                        }
                        markFemaleLineage={
                            margaTree ? false : showFemaleLineage
                        }
                        collapseDepth={undefined}
                        detachedPeople={margaDetachedRoots}
                        compact={fullscreen && fullscreenView === 'tree'}
                        nodeIdPrefix={
                            fullscreen
                                ? 'tarombo-fullscreen-tree-node'
                                : 'tarombo-desktop-tree-node'
                        }
                    />
                </div>
                {!margaTree &&
                    !treeHasChildren &&
                    !ancestorFocusId &&
                    noChildrenNotice}
            </div>
            {treeHasChildren && (
                <div className="absolute bottom-3 left-3 z-10">
                    {treeZoomControls}
                </div>
            )}
        </div>
    );

    if (people.length === 0) {
        return (
            <div className="flex h-full flex-1 flex-col p-4 md:p-6">
                <div className="mx-auto w-full max-w-md rounded-2xl border border-tb-outline-variant bg-tb-surface-bright p-6 text-center">
                    <h1 className="font-display text-2xl font-bold text-tb-on-surface">
                        Pohon Tarombo
                    </h1>
                    <p className="mt-2 text-sm text-tb-on-surface-variant">
                        Belum ada data tarombo. Tambahkan anggota terlebih
                        dahulu.
                    </p>
                </div>
            </div>
        );
    }

    return (
        <div
            className={cn(
                'flex flex-col gap-6 p-4 md:p-6',
                fullscreen ? 'h-dvh gap-4 md:p-4' : 'h-full flex-1',
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <div className={cn('flex', fullscreen && 'items-center gap-3')}>
                    {fullscreen &&
                        (fullscreenView === 'diagram' || margaTree) &&
                        backButton}
                    <div>
                        <h1 className="font-display text-2xl font-bold text-tb-on-surface md:text-3xl">
                            {margaTree
                                ? `Pohon Silsilah ${margaTree.direction === 'upper' ? 'Atas' : 'Bawah'}`
                                : 'Pohon Tarombo'}
                        </h1>
                        <p className="mt-1 text-sm text-tb-on-surface-variant">
                            {margaTree
                                ? `${margaTree.direction === 'upper' ? 'Jalur leluhur dari Si Raja Batak sampai' : 'Keturunan patrilineal dari'} ${margaIdentity?.name ?? margaTree.margaName}.`
                                : 'Visualisasi silsilah keluarga langsung dari database. Klik anggota untuk melihat detail.'}
                        </p>
                    </div>
                </div>
                {fullscreen && (
                    <div className="flex shrink-0 items-center gap-2">
                        <Button asChild size="sm" variant="outline">
                            <Link href={tarombo.snapshots.index()}>
                                <Images className="size-4" /> Galeri
                            </Link>
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            onClick={handleSaveSnapshot}
                            disabled={savingSnapshot}
                            className="text-tb-on-primary bg-tb-primary hover:bg-tb-primary/90"
                        >
                            {savingSnapshot ? (
                                <LoaderCircle className="size-4 animate-spin" />
                            ) : (
                                <Save className="size-4" />
                            )}
                            {savingSnapshot ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    </div>
                )}
            </div>

            <div
                className={cn(
                    'w-full',
                    fullscreen && 'flex min-h-0 flex-1 flex-col',
                )}
            >
                <div
                    className={cn(
                        'flex flex-wrap items-center gap-x-3 gap-y-2',
                        margaTree && 'hidden',
                    )}
                >
                    <span className="text-sm font-medium text-tb-on-surface">
                        Saya adalah:
                    </span>
                    {myPerson || identity?.request?.status === 'pending' ? (
                        <span className="inline-flex items-center gap-2 rounded-full border border-tb-outline-variant bg-tb-surface-bright px-3 py-1 text-sm font-medium text-tb-on-surface">
                            <span
                                aria-hidden
                                className="size-2 shrink-0 rounded-full"
                                style={{
                                    backgroundColor:
                                        margas.find(
                                            (marga) =>
                                                marga.name === myPerson?.marga,
                                        )?.color ?? 'var(--color-tb-primary)',
                                }}
                            />
                            <span className="max-w-48 truncate">
                                {myPerson?.name ??
                                    identity?.request?.personName}
                            </span>
                        </span>
                    ) : (
                        <span className="text-sm text-tb-on-surface-variant italic">
                            Belum dipilih
                        </span>
                    )}
                    {identity?.request?.status === 'pending' && (
                        <span className="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                            Menunggu persetujuan
                        </span>
                    )}
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => setPickerOpen(true)}
                        className="border-tb-outline-variant bg-tb-surface-bright text-tb-on-surface hover:bg-tb-surface-container hover:text-tb-on-surface"
                    >
                        <UserSearch className="size-4" /> Temukan Nama
                    </Button>
                    {myPerson && (
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={handleIdentityReset}
                            aria-label="Hapus pilihan nama saya"
                            title="Hapus pilihan"
                            className="text-tb-on-surface-variant hover:text-tb-on-surface"
                        >
                            <X className="size-4" />
                        </Button>
                    )}
                </div>

                <div
                    className={cn(
                        'relative mx-auto mb-4 w-full max-w-md',
                        margaTree && 'hidden',
                    )}
                    onBlur={(event) => {
                        if (
                            !event.currentTarget.contains(event.relatedTarget)
                        ) {
                            setSearchOpen(false);
                        }
                    }}
                >
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-tb-on-surface-variant" />
                    <Input
                        value={search}
                        onChange={(event) => {
                            setSearch(event.target.value);
                            setSearchOpen(true);
                        }}
                        onFocus={() => setSearchOpen(true)}
                        onKeyDown={(event) => {
                            if (event.key === 'Escape') {
                                setSearchOpen(false);
                            }
                        }}
                        placeholder="Cari nama anggota..."
                        aria-label="Cari nama anggota"
                        aria-expanded={searchOpen && normalizedSearch !== ''}
                        aria-controls="tarombo-search-results"
                        className="border-tb-outline-variant bg-tb-surface-bright pl-9 focus:border-tb-primary focus:ring-tb-primary/20"
                    />
                    {searchOpen && normalizedSearch !== '' && (
                        <div
                            id="tarombo-search-results"
                            role="listbox"
                            className="absolute z-30 mt-1 max-h-72 w-full overflow-y-auto rounded-lg border border-tb-outline-variant bg-tb-surface-bright p-1 shadow-lg"
                        >
                            {searchResults.length > 0 ? (
                                searchResults.map((person) => (
                                    <button
                                        key={person.id}
                                        type="button"
                                        role="option"
                                        aria-selected={
                                            person.id === ancestorFocusId
                                        }
                                        onClick={() => searchSelect(person)}
                                        className="flex w-full items-center justify-between gap-3 rounded-md px-3 py-2 text-left transition-colors hover:bg-tb-surface-container focus-visible:bg-tb-surface-container focus-visible:outline-none"
                                    >
                                        <span className="min-w-0">
                                            <span className="block truncate text-sm font-medium text-tb-on-surface">
                                                {person.name}
                                            </span>
                                            <span className="block truncate text-xs text-tb-on-surface-variant">
                                                {person.marga ||
                                                    'Marga belum dicatat'}
                                            </span>
                                        </span>
                                        <span className="shrink-0 text-[10px] font-semibold tracking-wide text-tb-primary uppercase">
                                            Lihat leluhur
                                        </span>
                                    </button>
                                ))
                            ) : (
                                <p className="px-3 py-4 text-center text-sm text-tb-on-surface-variant">
                                    Nama tidak ditemukan.
                                </p>
                            )}
                        </div>
                    )}
                </div>

                {fullscreen ? (
                    <div className="min-h-0 flex-1">
                        {fullscreenView === 'diagram'
                            ? renderDiagramCard(false)
                            : renderTreeCard(false)}
                    </div>
                ) : (
                    <>
                        {/* Desktop: Side by side */}
                        {expanded === null ? (
                            <div className="hidden gap-6 lg:grid lg:grid-cols-2">
                                {renderDiagramCard(false)}
                                {renderTreeCard(false)}
                            </div>
                        ) : (
                            <div className="hidden lg:block">
                                {expanded === 'diagram'
                                    ? renderDiagramCard(true)
                                    : renderTreeCard(true)}
                            </div>
                        )}

                        {/* Mobile: Tabs */}
                        <div className="lg:hidden">
                            <Tabs defaultValue="tree" className="w-full">
                                <TabsList className="grid w-full grid-cols-2">
                                    <TabsTrigger value="diagram">
                                        Diagram Radial
                                    </TabsTrigger>
                                    <TabsTrigger value="tree">
                                        Silsilah Pohon
                                    </TabsTrigger>
                                </TabsList>
                                <TabsContent value="diagram">
                                    <div className="rounded-2xl border border-tb-outline-variant bg-tb-surface-bright p-4">
                                        <TaromboDiagram
                                            onSelect={handleDiagramSelect}
                                            onPaneClick={() =>
                                                setSelectedId(null)
                                            }
                                            onBack={handleBack}
                                            canGoBack={history.length > 0}
                                            selectedId={selectedId ?? undefined}
                                            centerPersonId={centerPersonId}
                                            people={people}
                                            margas={margas}
                                            context="descendants"
                                        />
                                    </div>
                                </TabsContent>
                                <TabsContent value="tree">
                                    <div className="relative overflow-hidden rounded-2xl border border-tb-outline-variant bg-tb-surface-bright p-4">
                                        <div className="mb-4 border-b border-tb-outline-variant pb-3 text-center">
                                            <h3 className="font-display text-lg font-bold text-tb-on-surface">
                                                {margaTree
                                                    ? `Pohon Silsilah ${margaTree.direction === 'upper' ? 'Atas' : 'Bawah'}`
                                                    : 'Silsilah Keturunan'}
                                            </h3>
                                            <p className="mt-1 text-xs text-tb-on-surface-variant">
                                                {margaTree
                                                    ? `${margaTree.direction === 'upper' ? 'Si Raja Batak sampai' : 'Keturunan dari'} ${margaIdentity?.name ?? margaTree.margaName}`
                                                    : ancestorFocusPerson
                                                      ? `Jalur ${ANCESTOR_DEPTH} tingkat leluhur dari ${ancestorFocusPerson.name}`
                                                      : `Pohon vertikal dari ${treeCenterPerson?.name ?? 'Leluhur Utama'}`}
                                            </p>
                                            <div className="mt-3 flex flex-wrap items-center justify-center gap-3">
                                                {familyTreeSelector}
                                                {femaleLineageToggle}
                                            </div>
                                        </div>
                                        <div style={{ zoom: treeZoom }}>
                                            <DescendantsTree
                                                key={`${renderedTreeCenterId}-${margaTree?.direction ?? ancestorFocusId ?? 'branch'}-${showFemaleLineage ? 'with-female' : 'male-only'}`}
                                                people={renderedTreePeople}
                                                centerId={renderedTreeCenterId}
                                                onSelect={
                                                    margaTree
                                                        ? undefined
                                                        : handlePersonSelect
                                                }
                                                highlightId={
                                                    renderedHighlightId
                                                }
                                                editNodes={!margaTree}
                                                selectOnClick={!margaTree}
                                                showProfileOnName={!margaTree}
                                                readOnly={Boolean(margaTree)}
                                                alternativeTrees={
                                                    descendantAlternativeTrees
                                                }
                                                lineagePath={
                                                    margaTree?.direction ===
                                                    'upper'
                                                        ? margaLineagePath.map(
                                                              (person) =>
                                                                  person.id,
                                                          )
                                                        : lineagePath
                                                }
                                                markFemaleLineage={
                                                    showFemaleLineage
                                                }
                                                collapseDepth={undefined}
                                                detachedPeople={
                                                    margaDetachedRoots
                                                }
                                                nodeIdPrefix="tarombo-mobile-tree-node"
                                            />
                                        </div>
                                        {!treeHasChildren &&
                                            !ancestorFocusId &&
                                            noChildrenNotice}
                                        {treeHasChildren && (
                                            <div className="absolute bottom-3 left-3 z-10">
                                                {treeZoomControls}
                                            </div>
                                        )}
                                    </div>
                                </TabsContent>
                            </Tabs>
                        </div>
                    </>
                )}
            </div>

            <PersonTreePickerDialog
                open={pickerOpen}
                onOpenChange={setPickerOpen}
                people={selectablePeople}
                alternativeTrees={descendantAlternativeTrees}
                currentId={myId}
                onSelect={handleIdentitySelect}
            />
        </div>
    );
}
