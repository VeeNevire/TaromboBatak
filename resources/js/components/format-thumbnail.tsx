import type { CollageBox } from '@/lib/collage';

export function FormatThumbnail({
    boxes,
    className,
}: {
    boxes: CollageBox[];
    className?: string;
}) {
    return (
        <div
            className={`relative overflow-hidden rounded-md border-2 border-tb-primary bg-tb-surface-container ${className ?? ''}`}
        >
            {boxes.map((box, index) => (
                <div
                    key={index}
                    className="absolute border-2 border-tb-primary bg-tb-surface-bright/60"
                    style={{
                        left: `${box.x * 100}%`,
                        top: `${box.y * 100}%`,
                        width: `${box.w * 100}%`,
                        height: `${box.h * 100}%`,
                    }}
                />
            ))}
        </div>
    );
}
