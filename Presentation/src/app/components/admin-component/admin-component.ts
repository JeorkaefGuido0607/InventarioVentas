import { Component, inject, OnInit, ChangeDetectorRef } from '@angular/core';
import { UsuarioService } from '../../shared/services/usuario';
import { Usuario } from '../../shared/models/interfaces';
import { FrmUsuario } from '../forms/frm-usuario/frm-usuario';
import { MatDialog, MatDialogModule } from '@angular/material/dialog';

@Component({
  imports: [MatDialogModule],
  selector: 'app-admin-component',
  styleUrl: './admin-component.css',
  templateUrl: './admin-component.html',
})
export class AdminComponent implements OnInit {
  private readonly srvUsuario = inject(UsuarioService);
  private readonly dialogo = inject(MatDialog);
  private readonly detector = inject(ChangeDetectorRef);
  public usuarioAEliminar: Usuario | null = null;

  public usuarios: Usuario[] = [];
public totalUsuarios = 0;
public cargando = false;
public mensajeError = '';

public paginaActual = 1;
public registrosPorPagina = 10;

public filtrosActuales: {
  nombre?: string;
  username?: string;
} = {};

public get totalPaginas(): number {
  return Math.max(
    1,
    Math.ceil(this.totalUsuarios / this.registrosPorPagina)
  );
}

  ngOnInit(): void {
    this.listarUsuarios();
  }

public listarUsuarios(): void {
  this.cargando = true;
  this.mensajeError = '';

  const offset = (this.paginaActual - 1) * this.registrosPorPagina;

  this.srvUsuario.listar(
    offset,
    this.registrosPorPagina,
    this.filtrosActuales
  ).subscribe({
    next: (respuesta) => {
      this.usuarios = respuesta.data;
      this.totalUsuarios = respuesta.pagination.total;
      this.cargando = false;

      this.detector.markForCheck();
    },
    error: (error) => {
      console.error('Error al consultar usuarios:', error);
      this.mensajeError = 'No fue posible cargar los usuarios.';
      this.cargando = false;

      this.detector.markForCheck();
    }
  });
}

  public buscarUsuarios(valor: string, tipo: string): void {
  const termino = valor.trim();

  this.filtrosActuales = termino
    ? tipo === 'nombre'
      ? { nombre: termino }
      : { username: termino }
    : {};

  this.paginaActual = 1;
  this.listarUsuarios();
}

  public editarUsuario(usuario: Usuario): void {
    const dialogRef = this.dialogo.open(FrmUsuario, {
      width: '650px',
      maxWidth: '95vw',
      disableClose: true,
      data: usuario
    });

    dialogRef.afterClosed().subscribe((resultado) => {
      if (resultado === true) {
        this.listarUsuarios();
      }
    });
  }

  public eliminarUsuario(usuario: Usuario): void {
    this.usuarioAEliminar = usuario;
  }

  public cancelarEliminacion(): void {
    this.usuarioAEliminar = null;
  }

  public confirmarEliminacion(): void {
    if (!this.usuarioAEliminar) {
      return;
    }

    this.srvUsuario.eliminar(this.usuarioAEliminar.id).subscribe({
      next: () => {
        this.usuarioAEliminar = null;
        this.listarUsuarios();
      },
      error: (error) => {
        console.error('Error al eliminar usuario:', error);

        this.mensajeError =
          error.error?.error ||
          'No fue posible eliminar el usuario.';

        this.usuarioAEliminar = null;
      }
    });
  }

  public abrirFormulario(): void {
    const dialogRef = this.dialogo.open(FrmUsuario, {
      width: '650px',
      maxWidth: '95vw',
      disableClose: true
    });

    dialogRef.afterClosed().subscribe((resultado) => {
      if (resultado === true) {
        this.listarUsuarios();
      }
    });
  }
}
