import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { HttpErrorResponse } from '@angular/common/http';
import { AuthService } from '../../core/auth.service';

@Component({
  selector: 'app-login',
  imports: [FormsModule],
  templateUrl: './login.html',
  styleUrl: './login.css',
})
export class Login {
  private auth = inject(AuthService);
  private router = inject(Router);

  username = signal('');
  password = signal('');
  cargando = signal(false);
  error = signal('');

  async entrar() {
    if (!this.username().trim() || !this.password()) {
      this.error.set('Escribe tu usuario y contraseña.');
      return;
    }

    this.cargando.set(true);
    this.error.set('');

    try {
      await this.auth.login(this.username().trim(), this.password());
      await this.router.navigate(['/inicio']);
    } catch (e) {
      const status = (e as HttpErrorResponse).status;
      if (status === 401) this.error.set('Usuario o contraseña incorrectos.');
      else if (status === 0) this.error.set('No se pudo conectar con el servidor.');
      else this.error.set('Ocurrió un error. Intenta de nuevo.');
    } finally {
      this.cargando.set(false);
    }
  }
}