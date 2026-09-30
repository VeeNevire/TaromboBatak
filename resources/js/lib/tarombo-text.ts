import { fitInArea } from '@/lib/tarombo-compose';
import type { ComposeFrame } from '@/lib/tarombo-compose';

export type TextAlign = 'left' | 'center' | 'right';

/** A text layer's look; `size` is in frame canvas units like the placement. */
export type TextStyle = {
    content: string;
    font: string;
    size: number;
    color: string;
    bold: boolean;
    italic: boolean;
    align: TextAlign;
};

// Keep in sync with the font whitelist in SaveTaromboCompileDraftRequest.
export const TEXT_FONTS = [
    {
        value: 'Libre Caslon Text',
        stack: "'Libre Caslon Text', Georgia, serif",
    },
    { value: 'Cinzel', stack: "'Cinzel', Georgia, serif" },
    { value: 'Great Vibes', stack: "'Great Vibes', cursive" },
    { value: 'Inter', stack: "'Inter', Arial, sans-serif" },
    { value: 'Instrument Sans', stack: "'Instrument Sans', Arial, sans-serif" },
    { value: 'Georgia', stack: 'Georgia, serif' },
    { value: 'Times New Roman', stack: "'Times New Roman', serif" },
    { value: 'Arial', stack: 'Arial, sans-serif' },
    { value: 'Courier New', stack: "'Courier New', monospace" },
] as const;

export const TEXT_SIZE_MIN = 4;
export const TEXT_SIZE_MAX = 400;

export const defaultTextStyle = (frame: ComposeFrame): TextStyle => ({
    content: 'Teks baru',
    font: 'Libre Caslon Text',
    // About a twentieth of the frame width reads as a heading.
    size: Math.max(
        TEXT_SIZE_MIN,
        Math.round(Math.min(frame.canvas_width, frame.canvas_height) / 20),
    ),
    color: '#3b2a1a',
    bold: false,
    italic: false,
    align: 'center',
});

// Pixels drawn per frame canvas unit, so the text stays sharp at Produce size.
const RENDER_SCALE = 3;
const MAX_TEXT_CANVAS_SIDE = 4096;
const LINE_HEIGHT = 1.25;

export const fontStack = (font: string) =>
    TEXT_FONTS.find((option) => option.value === font)?.stack ??
    'Georgia, serif';

const cssFont = (style: TextStyle, px: number) =>
    `${style.italic ? 'italic ' : ''}${style.bold ? '700' : '400'} ${px}px ${fontStack(style.font)}`;

/**
 * Draws the text on a transparent canvas; `unitsPerPixel` converts its size
 * back to frame canvas units for the placement.
 */
export async function renderTextCanvas(
    style: TextStyle,
): Promise<{ canvas: HTMLCanvasElement; unitsPerPixel: number }> {
    const lines = (style.content || ' ').split('\n');
    const measure = document.createElement('canvas').getContext('2d');
    let scale = RENDER_SCALE;
    let px = style.size * scale;

    try {
        await document.fonts.load(cssFont(style, px), style.content || ' ');
    } catch {
        // A font that fails to load falls back to the next one in the stack.
    }

    const widest = () => {
        if (!measure) {
            return px * 4;
        }

        measure.font = cssFont(style, px);

        return Math.max(
            ...lines.map((line) => measure.measureText(line).width),
        );
    };

    let textWidth = widest();
    const padding = () => Math.ceil(px * 0.2);
    const size = () => ({
        width: Math.ceil(textWidth + padding() * 2),
        height: Math.ceil(lines.length * px * LINE_HEIGHT + padding() * 2),
    });

    // Long or huge text is drawn at a lower resolution instead of failing.
    const longest = Math.max(size().width, size().height);

    if (longest > MAX_TEXT_CANVAS_SIDE) {
        scale *= MAX_TEXT_CANVAS_SIDE / longest;
        px = style.size * scale;
        textWidth = widest();
    }

    const canvas = document.createElement('canvas');
    const { width, height } = size();
    canvas.width = Math.max(1, width);
    canvas.height = Math.max(1, height);

    const context = canvas.getContext('2d');

    if (context) {
        context.font = cssFont(style, px);
        context.fillStyle = style.color;
        context.textBaseline = 'middle';
        context.textAlign = style.align;

        const x =
            style.align === 'left'
                ? padding()
                : style.align === 'right'
                  ? canvas.width - padding()
                  : canvas.width / 2;

        lines.forEach((line, index) =>
            context.fillText(
                line,
                x,
                padding() + (index + 0.5) * px * LINE_HEIGHT,
            ),
        );
    }

    return { canvas, unitsPerPixel: 1 / scale };
}

/**
 * The text's box in frame canvas units: centred in the content area for new
 * text, otherwise kept at its position (anchored by its alignment).
 */
export function textPlacement(
    frame: ComposeFrame,
    canvas: HTMLCanvasElement,
    unitsPerPixel: number,
    align: TextAlign,
    current?: { x: number; y: number; width: number },
) {
    const width = Math.max(1, Math.round(canvas.width * unitsPerPixel));
    const height = Math.max(1, Math.round(canvas.height * unitsPerPixel));

    if (current) {
        const x =
            align === 'left'
                ? current.x
                : align === 'right'
                  ? current.x + current.width - width
                  : current.x + (current.width - width) / 2;

        return { x: Math.round(x), y: current.y, width, height };
    }

    const area = fitInArea(frame, width, height);

    return {
        x: Math.round(area.x + (area.width - width) / 2),
        y: Math.round(area.y + (area.height - height) / 2),
        width,
        height,
    };
}
