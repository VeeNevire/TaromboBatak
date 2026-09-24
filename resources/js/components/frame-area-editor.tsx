import { DraggableBox } from '@/components/draggable-box';

export type FrameArea = {
    area_x: number;
    area_y: number;
    area_width: number;
    area_height: number;
};

export function FrameAreaEditor({
    imageUrl,
    canvasWidth,
    canvasHeight,
    area,
    onChange,
}: {
    imageUrl: string;
    canvasWidth: number;
    canvasHeight: number;
    area: FrameArea;
    onChange: (area: FrameArea) => void;
}) {
    return (
        <div
            className="relative w-full overflow-hidden rounded-lg bg-tb-surface-container select-none"
            style={{ aspectRatio: `${canvasWidth} / ${canvasHeight}` }}
        >
            <img
                src={imageUrl}
                alt="Frame"
                draggable={false}
                className="pointer-events-none size-full object-fill"
            />
            <DraggableBox
                spaceWidth={canvasWidth}
                spaceHeight={canvasHeight}
                box={{
                    x: area.area_x,
                    y: area.area_y,
                    width: area.area_width,
                    height: area.area_height,
                }}
                onChange={(box) =>
                    onChange({
                        area_x: box.x,
                        area_y: box.y,
                        area_width: box.width,
                        area_height: box.height,
                    })
                }
                dimOutside
            />
        </div>
    );
}
