import type { Box } from '@/components/draggable-box';

export type ComposeFrame = {
    canvas_width: number;
    canvas_height: number;
    area_x: number;
    area_y: number;
    area_width: number;
    area_height: number;
};

// Keeps the canvas under the ~16.7M pixel limit of mobile Safari.
export const MAX_OUTPUT_SIDE = 4096;
const MAX_OUTPUT_PIXELS = 16_000_000;

// Colour distance (max channel difference) from the snapshot background.
const CLEAR_DISTANCE = 24;
const EDGE_DISTANCE = 64;

function imageToCanvas(image: HTMLImageElement): HTMLCanvasElement {
    const canvas = document.createElement('canvas');
    canvas.width = image.naturalWidth;
    canvas.height = image.naturalHeight;
    canvas.getContext('2d')?.drawImage(image, 0, 0);

    return canvas;
}

/**
 * Makes the solid background of a saved tree snapshot transparent and trims it to the tree.
 * The fill starts from the image border, so the inside of the node cards is kept.
 */
export function removeSnapshotBackground(
    image: HTMLImageElement,
): HTMLCanvasElement {
    const canvas = imageToCanvas(image);
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    const { width, height } = canvas;

    if (!ctx || width === 0 || height === 0) {
        return canvas;
    }

    const imageData = ctx.getImageData(0, 0, width, height);
    const px = imageData.data;
    const corners = [0, width - 1, (height - 1) * width, height * width - 1];

    // A transparent PNG snapshot has no background to remove.
    if (corners.some((i) => px[i * 4 + 3] < 250)) {
        return trimTransparent(canvas, ctx, imageData);
    }

    const bg = [0, 1, 2].map(
        (c) => corners.reduce((sum, i) => sum + px[i * 4 + c], 0) / 4,
    );
    const distance = (i: number) =>
        Math.max(
            Math.abs(px[i * 4] - bg[0]),
            Math.abs(px[i * 4 + 1] - bg[1]),
            Math.abs(px[i * 4 + 2] - bg[2]),
        );

    const visited = new Uint8Array(width * height);
    const stack = new Int32Array(width * height);
    let top = 0;
    const push = (i: number) => {
        if (!visited[i]) {
            visited[i] = 1;
            stack[top++] = i;
        }
    };

    for (let x = 0; x < width; x++) {
        push(x);
        push((height - 1) * width + x);
    }

    for (let y = 0; y < height; y++) {
        push(y * width);
        push(y * width + width - 1);
    }

    while (top > 0) {
        const i = stack[--top];
        const d = distance(i);

        if (d > EDGE_DISTANCE) {
            continue;
        }

        if (d > CLEAR_DISTANCE) {
            // Anti-aliased edge: keep it partly visible and remove the background tint.
            const alpha =
                (d - CLEAR_DISTANCE) / (EDGE_DISTANCE - CLEAR_DISTANCE);

            for (let c = 0; c < 3; c++) {
                px[i * 4 + c] = Math.min(
                    255,
                    Math.max(0, bg[c] + (px[i * 4 + c] - bg[c]) / alpha),
                );
            }

            px[i * 4 + 3] = Math.round(alpha * 255);

            continue;
        }

        px[i * 4 + 3] = 0;

        const x = i % width;

        if (x > 0) {
            push(i - 1);
        }

        if (x < width - 1) {
            push(i + 1);
        }

        if (i >= width) {
            push(i - width);
        }

        if (i < (height - 1) * width) {
            push(i + width);
        }
    }

    ctx.putImageData(imageData, 0, 0);

    return trimTransparent(canvas, ctx, imageData);
}

function trimTransparent(
    canvas: HTMLCanvasElement,
    ctx: CanvasRenderingContext2D,
    imageData: ImageData,
): HTMLCanvasElement {
    const { width, height } = canvas;
    const px = imageData.data;
    let minX = width;
    let minY = height;
    let maxX = -1;
    let maxY = -1;

    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            if (px[(y * width + x) * 4 + 3] > 8) {
                minX = Math.min(minX, x);
                maxX = Math.max(maxX, x);
                minY = Math.min(minY, y);
                maxY = Math.max(maxY, y);
            }
        }
    }

    if (maxX < 0) {
        return canvas;
    }

    const pad = Math.round(Math.max(maxX - minX, maxY - minY) * 0.02);
    const x = Math.max(0, minX - pad);
    const y = Math.max(0, minY - pad);
    const w = Math.min(width, maxX + pad + 1) - x;
    const h = Math.min(height, maxY + pad + 1) - y;
    const trimmed = document.createElement('canvas');
    trimmed.width = w;
    trimmed.height = h;
    trimmed.getContext('2d')?.putImageData(ctx.getImageData(x, y, w, h), 0, 0);

    return trimmed;
}

export function snapshotCanvas(
    image: HTMLImageElement,
    removeBackground: boolean,
): HTMLCanvasElement {
    return removeBackground
        ? removeSnapshotBackground(image)
        : imageToCanvas(image);
}

/** Default tree position: centred and uncropped inside the content area (frame canvas units). */
export function fitInArea(
    frame: ComposeFrame,
    width: number,
    height: number,
): Box {
    const fit = Math.min(
        frame.area_width / Math.max(1, width),
        frame.area_height / Math.max(1, height),
    );
    const w = width * fit;
    const h = height * fit;

    return {
        x: Math.round(frame.area_x + (frame.area_width - w) / 2),
        y: Math.round(frame.area_y + (frame.area_height - h) / 2),
        width: Math.round(w),
        height: Math.round(h),
    };
}

export type LayerKind = 'ranting' | 'background';

export type ComposeItem = {
    source: HTMLCanvasElement;
    crop?: Box | null;
    placement: Box;
};

/** Decodes an uploaded/pasted image into a canvas, shrunk so its longest side is at most maxSide. */
export function imageFileToCanvas(
    file: File,
    maxSide = MAX_OUTPUT_SIDE,
): Promise<HTMLCanvasElement> {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const image = new Image();

        image.onload = () => {
            const scale = Math.min(
                1,
                maxSide / Math.max(1, image.naturalWidth, image.naturalHeight),
            );
            const canvas = document.createElement('canvas');
            canvas.width = Math.max(1, Math.round(image.naturalWidth * scale));
            canvas.height = Math.max(1, Math.round(image.naturalHeight * scale));
            canvas
                .getContext('2d')
                ?.drawImage(image, 0, 0, canvas.width, canvas.height);
            URL.revokeObjectURL(url);
            resolve(canvas);
        };
        image.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('gagal memuat gambar'));
        };
        image.src = url;
    });
}

export function canvasThumbnail(source: HTMLCanvasElement, max = 64): string {
    const scale = Math.min(1, max / Math.max(1, source.width, source.height));
    const thumb = document.createElement('canvas');
    thumb.width = Math.max(1, Math.round(source.width * scale));
    thumb.height = Math.max(1, Math.round(source.height * scale));
    thumb.getContext('2d')?.drawImage(source, 0, 0, thumb.width, thumb.height);

    return thumb.toDataURL('image/png');
}

/** Covers the whole frame canvas, cropping the overflow (frame canvas units). */
export function coverFrame(
    frame: ComposeFrame,
    width: number,
    height: number,
): Box {
    const fit = Math.max(
        frame.canvas_width / Math.max(1, width),
        frame.canvas_height / Math.max(1, height),
    );
    const w = width * fit;
    const h = height * fit;

    return {
        x: Math.round((frame.canvas_width - w) / 2),
        y: Math.round((frame.canvas_height - h) / 2),
        width: Math.round(w),
        height: Math.round(h),
    };
}

/** Where a new layer starts: a background covers the frame, other images sit at half the content area. */
export function defaultLayerPlacement(
    kind: LayerKind,
    frame: ComposeFrame,
    width: number,
    height: number,
): Box {
    if (kind === 'background') {
        return coverFrame(frame, width, height);
    }

    const fit = fitInArea(frame, width, height);
    const w = Math.max(1, Math.round(fit.width * 0.5));
    const h = Math.max(1, Math.round(fit.height * 0.5));

    return {
        x: Math.round(fit.x + (fit.width - w) / 2),
        y: Math.round(fit.y + (fit.height - h) / 2),
        width: w,
        height: h,
    };
}

function outputLimit(frameImage: HTMLImageElement, maxSide: number): number {
    const frameWidth = frameImage.naturalWidth;
    const frameHeight = frameImage.naturalHeight;

    return Math.min(
        maxSide / Math.max(frameWidth, frameHeight),
        Math.sqrt(MAX_OUTPUT_PIXELS / (frameWidth * frameHeight)),
    );
}

function requiredScale(
    frameImage: HTMLImageElement,
    frame: ComposeFrame,
    source: Box,
    spot: Box,
): number {
    const spotWidth = Math.max(
        1,
        (spot.width * frameImage.naturalWidth) /
            Math.max(1, frame.canvas_width),
    );

    return Math.max(1, source.width / spotWidth);
}

function outputScale(
    frameImage: HTMLImageElement,
    frame: ComposeFrame,
    source: Box,
    spot: Box,
    maxSide: number,
): number {
    return Math.min(
        requiredScale(frameImage, frame, source, spot),
        outputLimit(frameImage, maxSide),
    );
}

/**
 * How many times the tree is enlarged beyond its own pixels in the produced image.
 * Above ~1.5 the node text starts to look blurry.
 */
export function treeUpscale(
    frameImage: HTMLImageElement,
    frame: ComposeFrame,
    source: Box,
    spot: Box,
): number {
    const scale = outputScale(frameImage, frame, source, spot, MAX_OUTPUT_SIDE);
    const drawnWidth =
        ((spot.width * frameImage.naturalWidth) /
            Math.max(1, frame.canvas_width)) *
        scale;

    return drawnWidth / Math.max(1, source.width);
}

function itemSource(item: ComposeItem): Box {
    return (
        item.crop ?? {
            x: 0,
            y: 0,
            width: item.source.width,
            height: item.source.height,
        }
    );
}

/**
 * Draws the frame, then every item bottom to top at its position in frame canvas units.
 * The output grows (up to maxSide) when an item is larger than its spot, so it keeps its detail.
 */
export function composeLayers(
    target: HTMLCanvasElement,
    frameImage: HTMLImageElement,
    frame: ComposeFrame,
    items: ComposeItem[],
    { maxSide = MAX_OUTPUT_SIDE }: { maxSide?: number } = {},
) {
    const frameWidth = frameImage.naturalWidth;
    const frameHeight = frameImage.naturalHeight;
    const ratioX = frameWidth / Math.max(1, frame.canvas_width);
    const ratioY = frameHeight / Math.max(1, frame.canvas_height);
    const wanted = items.reduce(
        (max, item) =>
            Math.max(
                max,
                requiredScale(
                    frameImage,
                    frame,
                    itemSource(item),
                    item.placement,
                ),
            ),
        1,
    );
    const scale = Math.min(wanted, outputLimit(frameImage, maxSide));

    target.width = Math.round(frameWidth * scale);
    target.height = Math.round(frameHeight * scale);

    const ctx = target.getContext('2d');

    if (!ctx) {
        return;
    }

    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(frameImage, 0, 0, target.width, target.height);

    for (const item of items) {
        const source = itemSource(item);
        const spot = item.placement;

        ctx.drawImage(
            item.source,
            source.x,
            source.y,
            source.width,
            source.height,
            spot.x * ratioX * scale,
            spot.y * ratioY * scale,
            spot.width * ratioX * scale,
            spot.height * ratioY * scale,
        );
    }
}

/** Draws the frame, then the (cropped) tree. */
export function composeOnFrame(
    target: HTMLCanvasElement,
    frameImage: HTMLImageElement,
    tree: HTMLCanvasElement,
    frame: ComposeFrame,
    {
        crop,
        placement,
        maxSide = MAX_OUTPUT_SIDE,
    }: { crop?: Box | null; placement?: Box | null; maxSide?: number } = {},
) {
    const source = crop ?? { x: 0, y: 0, width: tree.width, height: tree.height };

    composeLayers(
        target,
        frameImage,
        frame,
        [
            {
                source: tree,
                crop,
                placement:
                    placement ??
                    fitInArea(frame, source.width, source.height),
            },
        ],
        { maxSide },
    );
}
