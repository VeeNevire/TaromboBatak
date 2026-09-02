import { Download, File, FileAudio, FileVideo } from 'lucide-react';

export type ChatAttachment = {
    id: number | null;
    name: string;
    mime_type: string;
    size: number;
    category: 'image' | 'video' | 'audio' | 'file';
    url: string;
    download_url: string;
    file?: globalThis.File;
};

const sizeLabel = (bytes: number): string => {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
};

export function MessageAttachmentCard({
    attachment,
    mine,
}: {
    attachment: ChatAttachment;
    mine: boolean;
}) {
    if (attachment.category === 'image') {
        return (
            <a
                href={attachment.url}
                target="_blank"
                rel="noreferrer"
                className="block overflow-hidden rounded-xl"
            >
                <img
                    src={attachment.url}
                    alt={attachment.name}
                    className="max-h-72 w-full min-w-40 object-cover"
                    loading="lazy"
                />
            </a>
        );
    }

    if (attachment.category === 'video') {
        return (
            <video
                src={attachment.url}
                controls
                preload="metadata"
                className="max-h-72 w-full min-w-56 rounded-xl bg-black"
            >
                Browser Anda tidak mendukung pemutar video.
            </video>
        );
    }

    if (attachment.category === 'audio') {
        return (
            <div className="min-w-64 rounded-xl bg-black/8 p-2 dark:bg-white/10">
                <audio
                    src={attachment.url}
                    controls
                    preload="metadata"
                    className="h-10 w-full"
                />
                <p className="mt-1 truncate px-1 text-[11px] opacity-75">
                    {attachment.name}
                </p>
            </div>
        );
    }

    const Icon = attachment.mime_type.startsWith('video/')
        ? FileVideo
        : attachment.mime_type.startsWith('audio/')
          ? FileAudio
          : File;

    return (
        <a
            href={attachment.download_url}
            className={`flex min-w-52 items-center gap-3 rounded-xl border p-3 transition-colors ${
                mine
                    ? 'border-white/25 bg-white/10 hover:bg-white/15'
                    : 'bg-tb-surface-container-low border-tb-outline-variant hover:bg-tb-surface-container'
            }`}
        >
            <span className="grid size-10 shrink-0 place-items-center rounded-lg bg-black/8 dark:bg-white/10">
                <Icon className="size-5" />
            </span>
            <span className="min-w-0 flex-1">
                <span className="block truncate text-xs font-semibold">
                    {attachment.name}
                </span>
                <span className="mt-0.5 block text-[10px] opacity-70">
                    {sizeLabel(attachment.size)}
                </span>
            </span>
            <Download className="size-4 shrink-0 opacity-75" />
        </a>
    );
}

export function categoryForFile(
    file: globalThis.File,
): ChatAttachment['category'] {
    if (file.type.startsWith('image/')) {
        return 'image';
    }

    if (file.type.startsWith('video/')) {
        return 'video';
    }

    if (file.type.startsWith('audio/')) {
        return 'audio';
    }

    return 'file';
}
