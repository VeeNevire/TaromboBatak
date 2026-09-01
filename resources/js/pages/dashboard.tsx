import Home from '@/pages/home';
import type { HomeProps } from '@/pages/home';
import { dashboard } from '@/routes';

export default function Dashboard(props: HomeProps) {
    return <Home {...props} />;
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
