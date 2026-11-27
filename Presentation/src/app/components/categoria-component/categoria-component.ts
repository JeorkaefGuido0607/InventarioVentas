import { Component, inject, OnInit, ChangeDetectorRef } from '@angular/core';
import { CategoriaService } from '../../shared/services/categoria';
import { Categoria } from '../../shared/models/interfaces';
import { FormsModule } from '@angular/forms';
import { TokenService } from '../../shared/services/token-service';
import { MatDialog } from '@angular/material/dialog';
import { FrmCategoria } from '../forms/frm-categoria/frm-categoria';

@Component({
  imports: [FormsModule],
  selector: 'app-categoria-component',
  styleUrl: './categoria-component.css',
  templateUrl: './categoria-component.html',
})
export class CategoriaComponent implements OnInit {

  private readonly srvCategoria = inject(CategoriaService);
  private readonly detector = inject(ChangeDetectorRef);
  private readonly srvTokens = inject(TokenService);
  private readonly dialog = inject(MatDialog);

  public get puedeCrear(): boolean {
    return this.usuarioActual.rol === 'Admin' ||
      this.usuarioActual.rol === 'Bodeguero';
  }

  public categorias: Categoria[] = [];
  public totalCategorias = 0;
  public cargando = false;
  public mensajeError = '';
  public mostrarAlerta = false;

  public paginaActual = 1;
  public registrosPorPagina = 10;

  public filtroNombre = '';
  public filtroActivo = '';

  public get totalPaginas(): number {
    return Math.max(
      1,
      Math.ceil(this.totalCategorias / this.registrosPorPagina)
    );
  }

  ngOnInit(): void {
    console.log('Usuario actual:', this.usuarioActual);
    console.log('Rol del usuario:', this.usuarioActual.rol);

    this.listarCategorias();
  }

  public listarCategorias(): void {
    this.cargando = true;
    this.mensajeError = '';

    const offset = (this.paginaActual - 1) * this.registrosPorPagina;

    const filtros: {
      nombre?: string;
      activo?: boolean;
    } = {};

    if (this.filtroNombre.trim()) {
      filtros.nombre = this.filtroNombre.trim();
    }

    if (this.filtroActivo !== '') {
      filtros.activo = this.filtroActivo === 'true';
    }

    this.srvCategoria.listar(
      offset,
      this.registrosPorPagina,
      filtros
    ).subscribe({
      next: (respuesta) => {
        this.categorias = respuesta.data;
        this.totalCategorias = respuesta.pagination.total;
        this.cargando = false;
        this.detector.markForCheck();
      },
      error: (error) => {
        console.error('Error al consultar categorías:', error);
        this.mensajeError = 'No fue posible cargar las categorías.';
        this.cargando = false;
        this.detector.markForCheck();
      }
    });
  }

  public buscarCategorias(): void {
    this.paginaActual = 1;
    this.listarCategorias();
  }

  public usuarioActual = this.srvTokens.decodeToken() ?? {};

  public get puedeEditar(): boolean {
    return this.usuarioActual.rol === 'Admin' ||
      this.usuarioActual.rol === 'Bodeguero';
  }

  public get puedeEliminar(): boolean {
    return this.usuarioActual.rol === 'Admin';
  }

  public crearCategoria(): void {
    const dialogRef = this.dialog.open(FrmCategoria, {
      width: '500px',
      maxWidth: '95vw',
      data: null
    });

    dialogRef.afterClosed().subscribe(datos => {
      if (datos) {
        this.srvCategoria.crear(datos).subscribe({
          next: () => {
            this.listarCategorias();
          },
          error: (error) => {
            console.error('Error al crear categoría:', error);
            this.mensajeError = error.error?.message ||
              'No fue posible crear la categoría.';
            this.detector.markForCheck();
          }
        });
      }
    });
  }

  public editarCategoria(categoria: Categoria): void {
    const dialogRef = this.dialog.open(FrmCategoria, {
      width: '500px',
      maxWidth: '95vw',
      data: categoria
    });

    dialogRef.afterClosed().subscribe(datos => {
      if (datos) {
        this.srvCategoria.actualizar(categoria.id, datos).subscribe({
          next: () => {
            this.listarCategorias();
          },
          error: (error) => {
            console.error('Error al actualizar categoría:', error);
            this.mensajeError = error.error?.message ||
              'No fue posible actualizar la categoría.';
            this.detector.markForCheck();
          }
        });
      }
    });
  }

  public categoriaEliminar: Categoria | null = null;
  public mostrarConfirmacion = false;

  public eliminarCategoria(categoria: Categoria): void {
    this.categoriaEliminar = categoria;
    this.mostrarConfirmacion = true;
  }

  public cancelarEliminacion(): void {
    this.mostrarConfirmacion = false;
    this.categoriaEliminar = null;
  }

  public cerrarAlerta(): void {
    this.mostrarAlerta = false;
    this.mensajeError = '';
  }
  public confirmarEliminacion(): void {
    if (!this.categoriaEliminar) return;

    const id = this.categoriaEliminar.id;

    this.srvCategoria.eliminar(id).subscribe({
      next: () => {
        this.cancelarEliminacion();

        if (this.categorias.length === 1 && this.paginaActual > 1) {
          this.paginaActual--;
        }

        this.listarCategorias();
      },
      error: (error) => {
        console.error('Error al eliminar categoría:', error);

        this.cancelarEliminacion();

        this.mensajeError = error.status === 409
          ? 'No se puede eliminar esta categoría porque tiene productos asociados.'
          : error.error?.message || 'No fue posible eliminar la categoría.';

        this.mostrarAlerta = true;
        this.detector.markForCheck();
      }
    });
  }
}
