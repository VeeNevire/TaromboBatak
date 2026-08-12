import { AppHeaderUser } from '@/components/app-header-user';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { ThemeToggle } from '@/components/landing/theme-toggle';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="sticky top-0 left-0 z-30 overflow-hidden rounded-t-xl bg-tb-surface-bright/60 backdrop-blur-xl">
            <div className="mt-2 flex h-16 shrink-0 items-center gap-2 rounded-t-xl border-b border-tb-outline-variant/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
                <div className="flex items-center gap-2">
                    <SidebarTrigger className="-ml-1" />
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>
                <div className="ml-auto flex items-center gap-2">
                    <ThemeToggle />
                    <AppHeaderUser />
                </div>
            </div>
        </header>
    );
}
