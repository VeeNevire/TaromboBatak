import { Link, usePage } from '@inertiajs/react';
import { ChevronsUpDown, User } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { UserMenuContent } from '@/components/user-menu-content';
import { login } from '@/routes';

export function AppHeaderUser() {
    const { auth } = usePage().props;

    if (!auth.user) {
        return (
            <Link
                href={login()}
                className="inline-flex items-center gap-2 rounded-full bg-tb-primary px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-tb-primary-light"
            >
                <User className="size-4" />
                <span>Masuk / Daftar</span>
            </Link>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    className="flex items-center gap-2 rounded-full border border-tb-outline-variant py-1.5 pr-3 pl-1.5 text-left transition-colors hover:bg-tb-surface-container"
                    data-test="header-user-button"
                >
                    <UserInfo user={auth.user} />
                    <ChevronsUpDown className="size-4 text-tb-on-surface-variant" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                align="end"
            >
                <UserMenuContent user={auth.user} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
