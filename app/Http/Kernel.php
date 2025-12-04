<?php

protected $routeMiddleware = [
  'count.product.view' => \App\Http\Middleware\CountProductView::class,
];