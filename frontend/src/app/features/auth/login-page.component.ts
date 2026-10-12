import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { ActivatedRoute, Router } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';
import { AppError } from '../../core/models/app-error.model';

/**
 * Pantalla de acceso. Formulario reactivo tipado con etiqueta sobre el campo,
 * ayuda bajo el campo y error bajo el campo al que pertenece (design-system.md
 * §10). Los 422 se mapean campo por campo desde `error.details` y el 403 por
 * cuenta desactivada se distingue de unas credenciales incorrectas (C-03).
 */
@Component({
  selector: 'app-login-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    ReactiveFormsModule,
    MatCardModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatIconModule,
    MatProgressBarModule,
  ],
  templateUrl: './login-page.component.html',
  styleUrl: './login-page.component.scss',
})
export class LoginPageComponent {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);
  private readonly formBuilder = inject(FormBuilder);

  // Aviso general (credenciales, cuenta inactiva, limite o servidor) separado de
  // los errores por campo, que vive en los propios controles.
  protected readonly banner = signal<{ kind: 'error' | 'warning'; message: string } | null>(null);
  protected readonly submitting = signal(false);

  protected readonly form = this.formBuilder.nonNullable.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', [Validators.required]],
  });

  protected get emailControl() {
    return this.form.controls.email;
  }

  protected get passwordControl() {
    return this.form.controls.password;
  }

  protected onSubmit(): void {
    this.banner.set(null);
    this.clearFieldErrors();

    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.submitting.set(true);

    this.auth.login(this.form.getRawValue()).subscribe({
      next: () => {
        this.submitting.set(false);
        void this.router.navigateByUrl(this.redirectTarget());
      },
      error: (error: unknown) => {
        this.submitting.set(false);
        this.handleError(error);
      },
    });
  }

  private handleError(error: unknown): void {
    if (!(error instanceof AppError)) {
      this.banner.set({ kind: 'error', message: 'No se pudo iniciar sesion. Intente de nuevo.' });
      return;
    }

    // La cuenta desactivada es un 403 con codigo propio: se explica como tal en
    // lugar de confundirla con unas credenciales incorrectas.
    if (error.code === 'CUENTA_INACTIVA') {
      this.banner.set({
        kind: 'warning',
        message: 'Su cuenta esta desactivada. Contacte al administrador para reactivarla.',
      });
      return;
    }

    if (error.kind === 'validation') {
      this.applyFieldErrors(error);
      return;
    }

    // El backend responde 401 con un mensaje generico para no revelar si el
    // correo existe (C-03); conservo ese mensaje.
    this.banner.set({ kind: 'error', message: error.message });
  }

  private applyFieldErrors(error: AppError): void {
    let matched = false;

    for (const detail of error.details) {
      if (detail.field === 'email' || detail.field === 'password') {
        this.form.controls[detail.field].setErrors({ backend: detail.message });
        matched = true;
      }
    }

    if (!matched) {
      this.banner.set({ kind: 'error', message: error.message });
    }
  }

  private clearFieldErrors(): void {
    this.form.controls.email.setErrors(null);
    this.form.controls.password.setErrors(null);
  }

  private redirectTarget(): string {
    const redirect = this.route.snapshot.queryParamMap.get('redirect');
    // Evito redirecciones abiertas: solo acepto rutas internas.
    return redirect !== null && redirect.startsWith('/') ? redirect : '/dashboard';
  }
}
