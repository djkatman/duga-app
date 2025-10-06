<?php

return [
    'endpoint'  => env('APEX_API_ENDPOINT', 'https://affapi.duga.jp/search'),
    'app_id'    => env('APEX_APP_ID'),
    'agent_id'  => env('APEX_AGENT_ID'),
    'version'   => env('APEX_API_VERSION', '1.2'),
    'format'    => env('APEX_FORMAT', 'json'),
    'adult'     => env('APEX_ADULT', 1),
    'banner_id' => env('APEX_BANNER_ID'),
];