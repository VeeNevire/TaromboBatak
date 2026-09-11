import { useEffect, useMemo, useRef, useState } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

export type NameSuggestion = {
    id: number;
    name: string;
    alias?: string | null;
    gender?: string | null;
    spouse?: string | null;
    spouse_marga?: string | null;
    marga_id?: number | null;
    marga?: string | null;
    father_id?: number | null;
    father_name?: string | null;
    chain?: string | null;
};

type NameComboboxProps = {
    value: string;
    onChange: (value: string) => void;
    suggestions: Array<string | NameSuggestion>;
    onSelect?: (suggestion: NameSuggestion) => void;
    placeholder?: string;
    allowNa?: boolean;
    className?: string;
    showSiblingPreview?: boolean;
};

export function NameCombobox({
    value,
    onChange,
    suggestions,
    onSelect,
    placeholder,
    allowNa = true,
    className,
    showSiblingPreview = false,
}: NameComboboxProps) {
    const [open, setOpen] = useState(false);
    const [highlighted, setHighlighted] = useState(-1);
    const [hoveredSuggestion, setHoveredSuggestion] =
        useState<NameSuggestion | null>(null);
    const [hoverPosition, setHoverPosition] = useState({ x: 0, y: 0 });
    const boxRef = useRef<HTMLDivElement>(null);
    const blurTimer = useRef<number | null>(null);

    const query = value.trim().toLowerCase();

    const filtered = useMemo(() => {
        const matches = suggestions.filter((suggestion) => {
            const name =
                typeof suggestion === 'string'
                    ? suggestion
                    : suggestion.name;
            const context =
                typeof suggestion === 'string'
                    ? ''
                    : [suggestion.father_name, suggestion.marga, suggestion.chain]
                          .filter(Boolean)
                          .join(' ');

            return `${name} ${context}`.trim().toLowerCase().includes(query);
        });

        return matches.slice(0, 12);
    }, [suggestions, query]);

    const rows: Array<string | NameSuggestion> = allowNa
        ? [...filtered, '__NA__']
        : filtered;
    const siblingNames = useMemo(() => {
        if (hoveredSuggestion?.father_id == null) {
            return [];
        }

        return suggestions
            .filter(
                (suggestion): suggestion is NameSuggestion =>
                    typeof suggestion !== 'string' &&
                    suggestion.id !== hoveredSuggestion.id &&
                    suggestion.father_id === hoveredSuggestion.father_id,
            )
            .map((suggestion) => suggestion.name)
            .slice(0, 8);
    }, [hoveredSuggestion, suggestions]);

    useEffect(() => {
        return () => {
            if (blurTimer.current) {
                window.clearTimeout(blurTimer.current);
            }
        };
    }, []);

    const choose = (suggestion: string | NameSuggestion) => {
        const name =
            typeof suggestion === 'string' ? suggestion : suggestion.name;

        onChange(name === '__NA__' ? 'N/A' : name);

        if (typeof suggestion !== 'string') {
            onSelect?.(suggestion);
        }

        setOpen(false);
    };

    const onKeyDown = (e: React.KeyboardEvent) => {
        if (!open || rows.length === 0) {
            return;
        }

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setHighlighted((index) => (index + 1) % rows.length);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setHighlighted((index) => (index - 1 + rows.length) % rows.length);
        } else if (e.key === 'Enter' && highlighted >= 0) {
            e.preventDefault();
            choose(rows[highlighted]);
        } else if (e.key === 'Escape') {
            setOpen(false);
        }
    };

    return (
        <div ref={boxRef} className={cn('relative', className)}>
            <Input
                value={value}
                onChange={(e) => {
                    onChange(e.target.value);
                    setHighlighted(-1);
                    setOpen(true);
                }}
                onFocus={() => setOpen(true)}
                onBlur={() => {
                    blurTimer.current = window.setTimeout(() => setOpen(false), 150);
                }}
                onKeyDown={onKeyDown}
                placeholder={placeholder}
                className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
            />

            {open && rows.length > 0 && (
                <div className="absolute z-20 mt-1 max-h-56 w-full overflow-y-auto rounded-md border border-tb-outline-variant bg-tb-surface-bright p-1 shadow-lg">
                    {rows.map((row, index) => {
                        const isNa = row === '__NA__';
                        const suggestion =
                            typeof row === 'string' ? null : row;
                        const name = isNa
                            ? 'N/A'
                            : suggestion?.name ?? String(row);
                        const context = suggestion
                            ? suggestion.father_name
                                ? `Anak dari ${suggestion.father_name}`
                                : 'Belum memiliki data ayah'
                            : null;

                        return (
                            <button
                                key={suggestion?.id ?? `${name}-${index}`}
                                type="button"
                                onMouseDown={(e) => {
                                    e.preventDefault();
                                    choose(row);
                                }}
                                onMouseEnter={() => {
                                    setHighlighted(index);
                                    setHoveredSuggestion(suggestion);
                                }}
                                onMouseMove={(event) => {
                                    if (!showSiblingPreview || !suggestion) {
                                        return;
                                    }

                                    setHoverPosition({
                                        x: Math.min(
                                            event.clientX + 16,
                                            window.innerWidth - 288,
                                        ),
                                        y: Math.min(
                                            event.clientY + 16,
                                            window.innerHeight - 96,
                                        ),
                                    });
                                }}
                                onMouseLeave={() => setHoveredSuggestion(null)}
                                className={cn(
                                    'flex w-full flex-col items-start rounded px-2 py-1.5 text-left text-sm text-tb-on-surface',
                                    highlighted === index && 'bg-tb-surface-container',
                                    isNa && 'text-tb-on-surface-variant',
                                )}
                            >
                                <span>{isNa ? 'N/A (belum tahu)' : name}</span>
                                {context && (
                                    <span className="text-xs text-tb-on-surface-variant">
                                        {context}
                                        {suggestion?.marga
                                            ? ` - Marga ${suggestion.marga}`
                                            : ''}
                                        {suggestion?.chain
                                            ? ` · Chain ${suggestion.chain}`
                                            : ''}
                                    </span>
                                )}
                            </button>
                        );
                    })}
                </div>
            )}
            {showSiblingPreview && hoveredSuggestion && (
                <div
                    role="tooltip"
                    className="pointer-events-none fixed z-50 w-72 rounded-md border border-tb-primary/30 bg-tb-surface-bright px-3 py-2 shadow-lg"
                    style={{
                        left: `${hoverPosition.x}px`,
                        top: `${hoverPosition.y}px`,
                    }}
                >
                    <p className="text-xs font-semibold text-tb-on-surface">
                        {hoveredSuggestion.name}
                    </p>
                    <p className="mt-0.5 text-xs text-tb-on-surface-variant">
                        {hoveredSuggestion.father_id == null
                            ? 'Data ayah belum tersedia, jadi saudara kandung belum dapat ditentukan.'
                            : siblingNames.length > 0
                              ? `Saudara kandung: ${siblingNames.join(', ')}`
                              : 'Belum ada saudara kandung lain yang tercatat.'}
                    </p>
                </div>
            )}
        </div>
    );
}
