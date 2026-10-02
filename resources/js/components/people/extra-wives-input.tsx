import { Plus, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type WifeEntry = { name: string; marga: string };

export const emptyWifeEntry = (): WifeEntry => ({ name: '', marga: '' });

const MAX_WIVES = 10;

/**
 * Editable list of additional spouses for one person. Rows with an empty name
 * are ignored by the backend, so an untouched list is harmless.
 */
export function ExtraWivesInput({
    value,
    onChange,
    label = 'Pasangan Lainnya',
    addLabel = 'Tambah Pasangan',
    errors,
    errorPrefix,
    renderMarga,
}: {
    value: WifeEntry[];
    onChange: (next: WifeEntry[]) => void;
    label?: string;
    addLabel?: string;
    errors?: Record<string, string | undefined>;
    errorPrefix?: string;
    renderMarga?: (
        marga: string,
        onChange: (marga: string) => void,
    ) => ReactNode;
}) {
    const update = (index: number, patch: Partial<WifeEntry>) =>
        onChange(
            value.map((entry, i) =>
                i === index ? { ...entry, ...patch } : entry,
            ),
        );

    return (
        <div className="grid gap-2 sm:col-span-full">
            {value.length > 0 && (
                <Label className="text-tb-on-surface">{label}</Label>
            )}
            {value.map((entry, index) => (
                <div
                    key={index}
                    className="grid gap-2 rounded-lg border border-tb-outline-variant p-3 sm:grid-cols-[1fr_1fr_auto] sm:items-start"
                >
                    <div className="grid gap-1.5">
                        <Input
                            aria-label={`Nama pasangan tambahan ${index + 1}`}
                            placeholder="Nama pasangan"
                            value={entry.name}
                            onChange={(e) =>
                                update(index, { name: e.target.value })
                            }
                            className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                        />
                        {errorPrefix && (
                            <InputError
                                message={
                                    errors?.[`${errorPrefix}.${index}.name`]
                                }
                            />
                        )}
                    </div>
                    {renderMarga ? (
                        renderMarga(entry.marga, (marga) =>
                            update(index, { marga }),
                        )
                    ) : (
                        <Input
                            aria-label={`Marga pasangan tambahan ${index + 1}`}
                            placeholder="Marga pasangan"
                            value={entry.marga}
                            onChange={(e) =>
                                update(index, { marga: e.target.value })
                            }
                            className="border-tb-outline-variant bg-tb-surface-bright focus:border-tb-primary focus:ring-tb-primary/20"
                        />
                    )}
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label={`Hapus pasangan tambahan ${index + 1}`}
                        onClick={() =>
                            onChange(value.filter((_, i) => i !== index))
                        }
                    >
                        <Trash2 className="size-4" />
                    </Button>
                </div>
            ))}
            <Button
                type="button"
                variant="outline"
                size="sm"
                className="w-fit"
                disabled={value.length >= MAX_WIVES}
                onClick={() => onChange([...value, emptyWifeEntry()])}
            >
                <Plus className="size-4" /> {addLabel}
            </Button>
        </div>
    );
}
