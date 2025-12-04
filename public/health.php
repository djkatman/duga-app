<?php
echo json_encode([
  'DOCROOT' => $_SERVER['DOCUMENT_ROOT'] ?? null,
  '__DIR__' => __DIR__,
  'SCRIPT'  => $_SERVER['SCRIPT_FILENAME'] ?? null,
  'BASE'    => base_path(),
  'RES'     => resource_path('views'),
], JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
