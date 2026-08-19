import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import type { User } from '@/types';

export function UserInfo({
    user,
    showEmail = false,
    showRole = false,
    variant = 'default',
}: {
    user: User;
    showEmail?: boolean;
    showRole?: boolean;
    variant?: 'default' | 'sidebar';
}) {
    const getInitials = useInitials();
    const roleLabel =
        user.role === 'admin'
            ? 'Admin'
            : user.role === 'subadmin'
              ? 'Sub Admin'
              : null;

    return (
        <>
            <Avatar className="h-8 w-8 shrink-0 overflow-hidden rounded-full">
                <AvatarImage src={user.avatar} alt={user.name} />
                <AvatarFallback className="rounded-full bg-tb-primary/20 text-tb-primary dark:bg-tb-primary/30">
                    {getInitials(user.name)}
                </AvatarFallback>
            </Avatar>
            <div className="grid min-w-0 flex-1 text-left text-sm leading-tight">
                <span className="flex min-w-0 items-center gap-1.5 font-medium">
                    <span
                        className={cn(
                            'truncate',
                            variant === 'sidebar' && 'text-sidebar-foreground',
                        )}
                    >
                        {user.name}
                    </span>
                    {showRole && roleLabel && (
                        <span className="inline-flex shrink-0 items-center rounded-full bg-tb-primary/20 px-1.5 py-px text-[9px] font-semibold tracking-wide text-tb-primary uppercase">
                            {roleLabel}
                        </span>
                    )}
                </span>
                {showEmail && (
                    <span
                        className={cn(
                            'truncate text-xs',
                            variant === 'sidebar'
                                ? 'text-sidebar-foreground/70'
                                : 'text-muted-foreground',
                        )}
                    >
                        {user.email}
                    </span>
                )}
            </div>
        </>
    );
}
