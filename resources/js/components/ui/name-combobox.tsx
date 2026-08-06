import { useEffect, useMemo, useRef, useState } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type NameComboboxProps = {
    value: string;
    onChange: (value: string) => void;
    suggestions: string[];
    placeholder?: string;
    allowNa?: boolean;
    className?: string;
};

export function NameCombobox({
    value,
    onChange,
    suggestions,
    placeholder,
    allowNa = true,
    className,
}: NameComboboxProps) {
    const [open, setOpen] = useState(false);
    const [highlighted, setHighlighted] = useState(-1);
    const boxRef = useRef<HTMLDivElement>(null);
    const blurTimer = useRef<number | null>(null);

    const query = value.trim().toLowerCase();

    const filtered = useMemo(() => {
        const matches = suggestions.filter(
            (name) => name.trim().toLowerCase().includes(query) && name.trim() !== value.trim(),
        );

        return matches.slice(0, 12);
    }, [suggestions, query, value]);

    const rows = allowNa ? [...filtered, '__NA__'] : filtered;

    useEffect(() => {
        setHighlighted(-1);
    }, [value]);

    useEffect(() => {
        return () => {
            if (blurTimer.current) {
                window.clearTimeout(blurTimer.current);
            }
        };
    }, []);

    const choose = (name: string) => {
        onChange(name);
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
            choose(rows[highlighted] === '__NA__' ? 'N/A' : rows[highlighted]);
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
                        const name = isNa ? 'N/A' : row;

                        return (
                            <button
                                key={index}
                                type="button"
                                onMouseDown={(e) => {
                                    e.preventDefault();
                                    choose(name);
                                }}
                                onMouseEnter={() => setHighlighted(index)}
                                className={cn(
                                    'flex w-full items-center rounded px-2 py-1.5 text-left text-sm text-tb-on-surface',
                                    highlighted === index && 'bg-tb-surface-container',
                                    isNa && 'text-tb-on-surface-variant',
                                )}
                            >
                                {isNa ? 'N/A (belum tahu)' : name}
                            </button>
                        );
                    })}
                </div>
            )}
        </div>
    );
}