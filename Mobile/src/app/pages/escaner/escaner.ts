import { Component, inject, signal } from '@angular/core';
import { CurrencyPipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { HttpErrorResponse } from '@angular/common/http';
import { Producto } from '../../core/modelos';
import { ProductosService } from '../../core/productos.service';
import { EscanerService } from '../../core/escaner.service';

@Component({
  selector: 'app-escaner',
  imports: [FormsModule, CurrencyPipe, RouterLink],
  templateUrl: './escaner.html',
  styleUrl: './escaner.css',
})
export class Escaner {
  private productos = inject(ProductosService);
  escaner = inject(EscanerService);

  codigo = signal('');
  producto = signal<Producto | null>(null);
  mensaje = signal('');
  cargando = signal(false);

  async escanear() {
    this.mensaje.set('');
    try {
      const leido = await this.escaner.escanear();
      if (leido) {
        this.codigo.set(leido);
        await this.buscar();
      }
    } catch (e) {
      const texto = (e as { message?: string }).message ?? '';
      // Si la persona cancela el escaneo, no es un error
      if (!texto.toLowerCase().includes('cancel')) {
        this.mensaje.set(texto || 'No se pudo usar la cámara.');
      }
    }
  }

  async buscar() {
    const codigo = this.codigo().trim();
    if (!codigo) {
      this.mensaje.set('Escribe o escanea un código.');
      return;
    }

    this.cargando.set(true);
    this.mensaje.set('');
    this.producto.set(null);

    try {
      this.producto.set(await this.productos.porCodigo(codigo));
    } catch (e) {
      const status = (e as HttpErrorResponse).status;
      this.mensaje.set(
        status === 404 ? 'No existe un producto con ese código.' : 'No se pudo consultar el producto.',
      );
    } finally {
      this.cargando.set(false);
    }
  }
}