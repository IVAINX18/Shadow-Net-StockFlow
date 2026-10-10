<?php

use Tests\TestCase;

// Vinculo mis tests de Feature al TestCase de Laravel para tener el
// contenedor y las aserciones HTTP disponibles sin tocar la base de datos.
uses(TestCase::class)->in('Feature');
