export type CollageBox = { x: number; y: number; w: number; h: number };
export type CollageFormat = { id: string; label: string; boxes: CollageBox[] };

export const CANVAS_WIDTH = 1280;
export const CANVAS_HEIGHT = 720;
export const BOX_GAP = 10;

export const COLLAGE_FORMATS: CollageFormat[] = [
    {
        id: 'kiri-dua-kanan-satu',
        label: 'Format 1',
        boxes: [
            { x: 0, y: 0, w: 0.55, h: 0.5 },
            { x: 0, y: 0.5, w: 0.55, h: 0.5 },
            { x: 0.55, y: 0, w: 0.45, h: 1 },
        ],
    },
    {
        id: 'atas-dua-tengah-bawah',
        label: 'Format 2',
        boxes: [
            { x: 0, y: 0, w: 0.5, h: 0.35 },
            { x: 0.5, y: 0, w: 0.5, h: 0.35 },
            { x: 0, y: 0.35, w: 1, h: 0.3 },
            { x: 0, y: 0.65, w: 1, h: 0.35 },
        ],
    },
];

export function insetBox(box: CollageBox) {
    const x = box.x * CANVAS_WIDTH + BOX_GAP / 2;
    const y = box.y * CANVAS_HEIGHT + BOX_GAP / 2;
    const w = box.w * CANVAS_WIDTH - BOX_GAP;
    const h = box.h * CANVAS_HEIGHT - BOX_GAP;

    return { x, y, w, h };
}

export function loadImage(file: File): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = reject;
        img.src = url;
    });
}

export function drawImageCover(
    ctx: CanvasRenderingContext2D,
    img: HTMLImageElement,
    dx: number,
    dy: number,
    dw: number,
    dh: number,
) {
    const scale = Math.max(dw / img.width, dh / img.height);
    const sw = dw / scale;
    const sh = dh / scale;
    const sx = (img.width - sw) / 2;
    const sy = (img.height - sh) / 2;
    ctx.drawImage(img, sx, sy, sw, sh, dx, dy, dw, dh);
}

export function drawImageContain(
    ctx: CanvasRenderingContext2D,
    img: HTMLImageElement | HTMLCanvasElement,
    dx: number,
    dy: number,
    dw: number,
    dh: number,
) {
    const scale = Math.min(dw / img.width, dh / img.height);
    const w = img.width * scale;
    const h = img.height * scale;
    ctx.drawImage(img, dx + (dw - w) / 2, dy + (dh - h) / 2, w, h);
}

export function loadImageUrl(url: string): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = reject;
        img.src = url;
    });
}

export async function renderCollage(
    ctx: CanvasRenderingContext2D,
    format: CollageFormat,
    boxFiles: (File | null)[],
    cancelledRef?: { current: boolean },
) {
    ctx.fillStyle = '#f0ebd8';
    ctx.fillRect(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT);

    for (let i = 0; i < format.boxes.length; i++) {
        const { x, y, w, h } = insetBox(format.boxes[i]);
        const file = boxFiles[i];

        if (file) {
            const img = await loadImage(file);

            if (cancelledRef?.current) {
                return;
            }

            drawImageCover(ctx, img, x, y, w, h);
        } else {
            ctx.fillStyle = '#fdfcf7';
            ctx.fillRect(x, y, w, h);
            ctx.strokeStyle = '#b34b1e';
            ctx.setLineDash([8, 6]);
            ctx.strokeRect(x, y, w, h);
            ctx.setLineDash([]);
        }
    }
}

export function composeCanvasToFile(
    canvas: HTMLCanvasElement,
    filename = 'frame-kolase.jpg',
): Promise<File | null> {
    return new Promise((resolve) => {
        canvas.toBlob(
            (blob) => {
                if (!blob) {
                    resolve(null);

                    return;
                }

                resolve(new File([blob], filename, { type: 'image/jpeg' }));
            },
            'image/jpeg',
            0.92,
        );
    });
}
