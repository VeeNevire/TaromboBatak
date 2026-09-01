<?php

return [
    'public_max_depth' => (int) env('TAROMBO_PUBLIC_MAX_DEPTH', 11),
    'public_max_nodes' => (int) env('TAROMBO_PUBLIC_MAX_NODES', 500),
    'person_max_depth' => (int) env('TAROMBO_PERSON_MAX_DEPTH', 5),
    'person_max_nodes' => (int) env('TAROMBO_PERSON_MAX_NODES', 500),
];
