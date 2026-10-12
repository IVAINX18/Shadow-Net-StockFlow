<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    // Llevo la cookie de sesión de la respuesta a las siguientes peticiones
    // porque el cliente de pruebas no la conserva solo como haría un navegador
    // real; sin esto ninguna prueba ejercería la sesión por cookie de verdad.
    public function conservarSesion(TestResponse $respuesta): void
    {
        $nombre = (string) config('session.cookie');
        $galleta = $respuesta->getCookie($nombre, false);

        if ($galleta !== null) {
            $this->withUnencryptedCookie($nombre, $galleta->getValue());
        }

        // Descarto los guardias cacheados para que la siguiente petición
        // resuelva al usuario desde la cookie y no desde la memoria del
        // proceso de pruebas.
        $this->app['auth']->forgetGuards();
    }
}
