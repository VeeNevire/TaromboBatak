import type { CSSProperties } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export type TreeFontFamily = 'sans' | 'serif' | 'mono';

export type TreeSettings = {
    name_box_bg: string;
    name_box_border: string;
    initial_ring: string;
    font_color: string;
    font_size: number;
    font_family: TreeFontFamily;
    font_bold: boolean;
    branch_color: string;
    branch_width: number;
    lineage_color: string;
};

// Same look the tree has without any saved setting (fullscreen uses the compact 8px name size).
export const DEFAULT_TREE_SETTINGS: TreeSettings = {
    name_box_bg: '#ffffff',
    name_box_border: '#e3dfd2',
    initial_ring: '#e3dfd2',
    font_color: '#24322b',
    font_size: 8,
    font_family: 'sans',
    font_bold: false,
    branch_color: '#a79e8c',
    branch_width: 1,
    lineage_color: '#dc2626',
};

const FONT_FAMILIES: Record<TreeFontFamily, { label: string; css: string }> = {
    sans: {
        label: 'Sans (bawaan)',
        css: "'Instrument Sans', ui-sans-serif, system-ui, sans-serif",
    },
    serif: {
        label: 'Serif',
        css: "'Libre Caslon Text', ui-serif, Georgia, serif",
    },
    mono: {
        label: 'Monospace',
        css: 'ui-monospace, SFMono-Regular, Menlo, monospace',
    },
};

/** CSS variables read by the node cards, branch lines and the red lineage line. */
export function treeSettingsStyle(settings: TreeSettings): CSSProperties {
    return {
        '--tb-name-bg': settings.name_box_bg,
        '--tb-name-border': settings.name_box_border,
        '--tb-ring': settings.initial_ring,
        '--tb-name-color': settings.font_color,
        '--tb-name-size': `${settings.font_size}px`,
        '--tb-name-font': FONT_FAMILIES[settings.font_family].css,
        '--tb-name-weight': settings.font_bold ? 700 : 600,
        '--tb-branch-color': settings.branch_color,
        '--tb-branch-width': `${settings.branch_width}px`,
        '--tb-lineage-color': settings.lineage_color,
    } as CSSProperties;
}

function ColorField({
    label,
    value,
    onChange,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <label className="flex items-center justify-between gap-3 text-sm text-tb-on-surface">
            {label}
            <span className="flex items-center gap-2">
                <span className="font-mono text-xs text-tb-on-surface-variant uppercase">
                    {value}
                </span>
                <input
                    type="color"
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    className="h-8 w-12 cursor-pointer rounded border border-tb-outline-variant bg-transparent p-0.5"
                />
            </span>
        </label>
    );
}

function SliderField({
    label,
    value,
    min,
    max,
    step,
    unit,
    onChange,
}: {
    label: string;
    value: number;
    min: number;
    max: number;
    step: number;
    unit: string;
    onChange: (value: number) => void;
}) {
    return (
        <label className="flex items-center justify-between gap-3 text-sm text-tb-on-surface">
            {label}
            <span className="flex items-center gap-2">
                <input
                    type="range"
                    min={min}
                    max={max}
                    step={step}
                    value={value}
                    onChange={(event) => onChange(Number(event.target.value))}
                    className="w-32 accent-tb-primary"
                />
                <span className="w-12 text-right text-xs tabular-nums">
                    {value}
                    {unit}
                </span>
            </span>
        </label>
    );
}

function Section({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-3 rounded-lg border border-tb-outline-variant p-3">
            <p className="text-xs font-semibold tracking-wide text-tb-primary uppercase">
                {title}
            </p>
            {children}
        </div>
    );
}

export function TreeSettingsDialog({
    open,
    onOpenChange,
    settings,
    onChange,
    onSave,
    onReset,
    saving,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    settings: TreeSettings;
    onChange: (settings: TreeSettings) => void;
    onSave: () => void;
    onReset: () => void;
    saving: boolean;
}) {
    const set = <K extends keyof TreeSettings>(
        key: K,
        value: TreeSettings[K],
    ) => onChange({ ...settings, [key]: value });

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Setting Tampilan Pohon</DialogTitle>
                    <DialogDescription>
                        Perubahan langsung terlihat di pohon. Pengaturan
                        disimpan di akun Anda dan ikut di perangkat lain.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-3">
                    <Section title="Kotak nama">
                        <ColorField
                            label="Isi kotak"
                            value={settings.name_box_bg}
                            onChange={(value) => set('name_box_bg', value)}
                        />
                        <ColorField
                            label="Garis kotak"
                            value={settings.name_box_border}
                            onChange={(value) => set('name_box_border', value)}
                        />
                    </Section>

                    <Section title="Lingkaran inisial">
                        <ColorField
                            label="Garis lingkaran"
                            value={settings.initial_ring}
                            onChange={(value) => set('initial_ring', value)}
                        />
                    </Section>

                    <Section title="Font nama">
                        <ColorField
                            label="Warna"
                            value={settings.font_color}
                            onChange={(value) => set('font_color', value)}
                        />
                        <SliderField
                            label="Ukuran"
                            value={settings.font_size}
                            min={6}
                            max={16}
                            step={1}
                            unit="px"
                            onChange={(value) => set('font_size', value)}
                        />
                        <div className="flex items-center justify-between gap-3">
                            <Label>Jenis huruf</Label>
                            <Select
                                value={settings.font_family}
                                onValueChange={(value) =>
                                    set('font_family', value as TreeFontFamily)
                                }
                            >
                                <SelectTrigger className="w-44">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {(
                                        Object.keys(
                                            FONT_FAMILIES,
                                        ) as TreeFontFamily[]
                                    ).map((key) => (
                                        <SelectItem key={key} value={key}>
                                            {FONT_FAMILIES[key].label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <label className="flex items-center gap-2 text-sm text-tb-on-surface">
                            <Checkbox
                                checked={settings.font_bold}
                                onCheckedChange={(value) =>
                                    set('font_bold', value === true)
                                }
                            />
                            Tebal (bold)
                        </label>
                    </Section>

                    <Section title="Garis ranting">
                        <ColorField
                            label="Warna"
                            value={settings.branch_color}
                            onChange={(value) => set('branch_color', value)}
                        />
                        <SliderField
                            label="Ketebalan"
                            value={settings.branch_width}
                            min={0.5}
                            max={4}
                            step={0.5}
                            unit="px"
                            onChange={(value) => set('branch_width', value)}
                        />
                    </Section>

                    <Section title="Garis merah (jalur pencarian)">
                        <ColorField
                            label="Warna"
                            value={settings.lineage_color}
                            onChange={(value) => set('lineage_color', value)}
                        />
                    </Section>
                </div>

                <div className="flex items-center justify-between gap-2 pt-1">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={saving}
                        onClick={onReset}
                    >
                        Kembalikan bawaan
                    </Button>
                    <Button
                        type="button"
                        disabled={saving}
                        onClick={onSave}
                        className="text-tb-on-primary bg-tb-primary hover:bg-tb-primary-light"
                    >
                        {saving ? 'Menyimpan...' : 'Simpan'}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
