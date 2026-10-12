<?php

// Permito a la SPA propia (origen del entorno activo) llamar a la API con la
// cookie de sesión: sin este archivo la respuesta sale con ACAO "*" y el
// navegador bloquea toda petición con credenciales (C-06 exige cookie).
return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:4200')],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
