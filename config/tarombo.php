<?php

return [
    'public_max_depth' => (int) env('TAROMBO_PUBLIC_MAX_DEPTH', 11),
    'public_max_nodes' => (int) env('TAROMBO_PUBLIC_MAX_NODES', 500),
    'person_max_depth' => (int) env('TAROMBO_PERSON_MAX_DEPTH', 5),
    'person_max_nodes' => (int) env('TAROMBO_PERSON_MAX_NODES', 500),

    // Authenticated dashboard views (e.g. a marga's Silsilah Bawah) are not
    // exposed to anonymous traffic, so they can afford a much deeper limit
    // than the public homepage tree without the same abuse/perf concerns.
    'dashboard_max_depth' => (int) env('TAROMBO_DASHBOARD_MAX_DEPTH', 40),
    'dashboard_max_nodes' => (int) env('TAROMBO_DASHBOARD_MAX_NODES', 3000),
];
