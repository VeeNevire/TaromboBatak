import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export type MargaOption = { id: number; name: string };

const VALUE_NONE = '__none__';
const VALUE_LEGACY = '__legacy__';

/**
 * Spouse marga picker limited to the registered marga list. Spouse marga is
 * stored by name, so a legacy free-text value that is not in the list is kept
 * as its own option until the user picks a registered marga.
 */
export function SpouseMargaSelect({
    value,
    margas,
    onChange,
    placeholder = 'Pilih marga pasangan',
    ariaLabel,
    id,
}: {
    value: string;
    margas: MargaOption[];
    onChange: (value: string) => void;
    placeholder?: string;
    ariaLabel?: string;
    id?: string;
}) {
    const matched = margas.find((marga) => marga.name === value);
    const isLegacy = value.trim() !== '' && !matched;
    const selected = matched
        ? String(matched.id)
        : isLegacy
          ? VALUE_LEGACY
          : VALUE_NONE;

    return (
        <Select
            value={selected}
            onValueChange={(next) => {
                if (next === VALUE_LEGACY) {
                    return;
                }

                onChange(
                    next === VALUE_NONE
                        ? ''
                        : (margas.find((marga) => String(marga.id) === next)
                              ?.name ?? ''),
                );
            }}
        >
            <SelectTrigger
                id={id}
                aria-label={ariaLabel}
                className="w-full border-tb-outline-variant bg-tb-surface-bright"
            >
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={VALUE_NONE}>— Tidak ada —</SelectItem>
                {isLegacy && (
                    <SelectItem value={VALUE_LEGACY}>{value}</SelectItem>
                )}
                {margas.map((marga) => (
                    <SelectItem key={marga.id} value={String(marga.id)}>
                        {marga.name}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
