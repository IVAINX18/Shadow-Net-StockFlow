import { Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';

/**
 * Componente raiz. Solo hospeda el enrutador: el armazon (navegacion y barra
 * superior) es una ruta hija, de modo que la pantalla de acceso se renderiza
 * fuera del armazon.
 */
@Component({
  selector: 'app-root',
  imports: [RouterOutlet],
  templateUrl: './app.html',
  styleUrl: './app.scss',
})
export class App {}
