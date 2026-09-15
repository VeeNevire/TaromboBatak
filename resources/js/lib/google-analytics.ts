type GoogleAnalyticsParameters = Record<
    string,
    string | number | boolean | undefined
>;

declare global {
    interface Window {
        gtag?: (
            command: 'event',
            eventName: string,
            parameters?: GoogleAnalyticsParameters,
        ) => void;
    }
}

let lastTrackedPath: string | null = null;

/** Track a virtual page view after an Inertia navigation. */
export function trackGoogleAnalyticsPageView(url: string): void {
    if (!window.gtag) {
        return;
    }

    const location = new URL(url, window.location.origin);
    const path = `${location.pathname}${location.search}`;

    if (path === lastTrackedPath) {
        return;
    }

    lastTrackedPath = path;
    window.gtag('event', 'page_view', {
        page_location: location.href,
        page_path: path,
        page_title: document.title,
    });
}

/** Send an aggregate product event. Do not include names, email addresses, or message contents. */
export function trackGoogleAnalyticsEvent(
    eventName: string,
    parameters: GoogleAnalyticsParameters = {},
): void {
    window.gtag?.('event', eventName, parameters);
}
