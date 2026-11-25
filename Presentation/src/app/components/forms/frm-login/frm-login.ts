import { Component, signal, inject } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { FormField, FormRoot, form, required } from '@angular/forms/signals';
import { MatDialogRef } from '@angular/material/dialog';
import { LoginForm } from '../../../shared/models/interfaces';
import { Router } from '@angular/router';
import { AuthService } from '../../../shared/services/auth-service';

@Component({
  imports: [MatCardModule, MatIconModule, MatButtonModule, MatFormFieldModule,
    MatInputModule, FormRoot, FormField],

  selector: 'app-frm-login',
  styleUrl: './frm-login.css',
  templateUrl: './frm-login.html',
})
export class FrmLogin {
  dialogRef = inject(MatDialogRef<FrmLogin>);
  private readonly srvAuth = inject(AuthService);
  private readonly router = inject(Router);

  public mostrarError = signal(false);

  objLogin = signal<LoginForm>({
    username: '',
    password: ''
  });

  frmLogin = form(this.objLogin, (s) => {
    required(s.username, { message: 'Usuario es requerido' });
    required(s.password, { message: 'Contraseña es requerida' });
  },
  {
    submission: {
      action: async () => {
        this.loggin(this.objLogin());
      }
    }
  });

  loggin(datos: LoginForm) {
    this.srvAuth.login(datos).subscribe({
      next: (respuesta) => {
        this.srvAuth.doLogin(respuesta);
        this.dialogRef.close();
        this.router.navigate(['/home']);
      },
      error: () => {
        this.mostrarError.set(true);
      }
    });
  }
}
