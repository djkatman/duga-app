<?php

return [
    'endpoint'  => env('APEX_API_ENDPOINT', 'https://affapi.duga.jp/search'),
    'app_id'    => env('APEX_APP_ID'),
    'agent_id'  => env('APEX_AGENT_ID'),
    'version'   => env('APEX_API_VERSION', '1.2'),
    'format'    => env('APEX_FORMAT', 'json'),
    'adult'     => env('APEX_ADULT', 1),
    'banner_id' => env('APEX_BANNER_ID'),

    'qps'                => (int) env('DUGA_QPS', 2),
    'cooldown'           => (int) env('DUGA_COOLDOWN', 5),
    'pacer_interval_ms'  => (int) env('DUGA_PACER_INTERVAL_MS', 1100),
    'rate_per_min'       => (int) env('DUGA_RATE_PER_MIN', 60),
    'circuit_seconds'    => (int) env('DUGA_CIRCUIT_SECONDS', 60),
];