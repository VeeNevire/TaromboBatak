import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';

export function AppAvatar({
    name,
    image,
    color,
    className,
}: {
    name: string;
    image?: string | null;
    color?: string | null;
    className?: string;
}) {
    const getInitials = useInitials();

    return (
        <Avatar
            className={cn(
                'size-9 shrink-0 overflow-hidden rounded-full',
                className,
            )}
        >
            {image ? <AvatarImage src={image} alt={name} /> : null}
            <AvatarFallback
                className={cn(
                    'text-sm font-semibold text-white',
                    !color && 'bg-tb-primary',
                )}
                style={color ? { backgroundColor: color } : undefined}
            >
                {getInitials(name)}
            </AvatarFallback>
        </Avatar>
    );
}
