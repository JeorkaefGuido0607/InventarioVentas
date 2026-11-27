

import { Component, inject, OnInit } from '@angular/core';
import {
  FormBuilder,
  ReactiveFormsModule,
  Validators
} from '@angular/forms';
import {
  MatDialogRef,
  MAT_DIALOG_DATA
} from '@angular/material/dialog';

import { UsuarioService } from '../../../shared/services/usuario';
import { Rol, Usuario } from '../../../shared/models/interfaces';

@Component({
  imports: [ReactiveFormsModule],
  selector: 'app-frm-usuario',
  styleUrl: './frm-usuario.css',
  templateUrl: './frm-usuario.html',
})
export class FrmUsuario implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly srvUsuario = inject(UsuarioService);
  private readonly dialogRef = inject(MatDialogRef<FrmUsuario>);

  public usuarioEditar = inject<Usuario | null>(MAT_DIALOG_DATA, {
    optional: true
  });

  public roles: Rol[] = [];
  public cargando = false;
  public mensajeError = '';

  public get modoEdicion(): boolean {
    return this.usuarioEditar !== null;
  }

  public formulario = this.fb.group({
    username: ['', [
      Validators.required,
      Validators.minLength(8),
      Validators.maxLength(16),
      Validators.pattern(/^[a-z0-9]{8,16}$/)
    ]],

    nombre_completo: ['', [
      Validators.required,
      Validators.minLength(6),
      Validators.maxLength(150),
      Validators.pattern(/^[A-Za-zÑñÁÉÍÓÚáéíóú]{2,}(?: [A-Za-zÑñÁÉÍÓÚáéíóú]{2,}){1,3}$/)
    ]],

    correo: ['', [
      Validators.required,
      Validators.email,
      Validators.minLength(5),
      Validators.maxLength(150)
    ]],

    password: [''],

    confirmarPassword: [''],

    rol_id: [null as number | null, Validators.required]
  });

  ngOnInit(): void {
    this.cargarRoles();

    if (this.usuarioEditar) {
      this.formulario.patchValue({
        username: this.usuarioEditar.username,
        nombre_completo: this.usuarioEditar.nombre_completo,
        correo: this.usuarioEditar.correo,
        rol_id: this.usuarioEditar.rol_id
      });

      this.formulario.controls.username.disable();
    } else {
      this.formulario.controls.password.setValidators([
        Validators.required,
        Validators.minLength(8),
        Validators.maxLength(64),
        Validators.pattern(/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[#$@*])[\s\S]{8,64}$/)
      ]);
    }
  }

  public cargarRoles(): void {
  this.srvUsuario.obtenerRoles().subscribe({
    next: (respuesta) => {
      console.log('Respuesta de roles:', respuesta);

      this.roles = respuesta.data;

      console.log('Roles cargados en el componente:', this.roles);
    },
    error: (error) => {
      console.error('Error al cargar roles:', error);
      this.mensajeError = 'No fue posible cargar los roles.';
    }
  });
}

  public guardar(): void {
    this.mensajeError = '';

    if (this.formulario.invalid) {
      this.formulario.markAllAsTouched();
      return;
    }

    const datos = this.formulario.getRawValue();

    if (datos.password !== datos.confirmarPassword) {
      if (datos.password || datos.confirmarPassword) {
        this.mensajeError = 'Las contraseñas no coinciden.';
        return;
      }
    }

    const usuario: any = {
      nombre_completo: datos.nombre_completo!,
      correo: datos.correo!,
      rol_id: datos.rol_id!
    };

    if (!this.modoEdicion) {
      usuario.username = datos.username!;
      usuario.password = datos.password!;
    } else if (datos.password) {
      usuario.password = datos.password;
    }

    this.cargando = true;

    const peticion = this.modoEdicion
      ? this.srvUsuario.actualizar(this.usuarioEditar!.id, usuario)
      : this.srvUsuario.crear(usuario);

    peticion.subscribe({
      next: () => {
        this.cargando = false;
        this.dialogRef.close(true);
      },
      error: (error) => {
        this.cargando = false;
        this.mensajeError =
          error.error?.error ||
          error.error?.errores?.body ||
          'No fue posible guardar el usuario.';
      }
    });
  }

  public cancelar(): void {
    this.dialogRef.close(false);
  }
}
