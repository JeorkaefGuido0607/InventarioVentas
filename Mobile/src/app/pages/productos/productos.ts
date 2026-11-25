import { Component, inject, OnInit, signal } from '@angular/core';
import { CurrencyPipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { Producto } from '../../core/modelos';
import { ProductosService } from '../../core/productos.service';

@Component({
  selector: 'app-productos',
  imports: [FormsModule, CurrencyPipe, RouterLink],
  templateUrl: './productos.html',
  styleUrl: './productos.css',
})
export class Productos implements OnInit {
  private servicio = inject(ProductosService);
  private readonly LIMITE = 10;

  productos = signal<Producto[]>([]);
  total = signal(0);
  busqueda = signal('');
  cargando = signal(false);
  error = signal('');

  ngOnInit() {
    this.buscar();
  }

  // empieza desde cero
  async buscar() {
    this.productos.set([]);
    await this.cargar(0);
  }

  // pide la siguiente página y la agrega a la lista
  async masResultados() {
    await this.cargar(this.productos().length);
  }
  // Al borrar el campo por completo, vuelve la lista completa
alEscribir(texto: string) {
  this.busqueda.set(texto);
  if (texto.trim() === '') {
    this.buscar();
  }
}

//  borra el texto y recarga todos los productos
limpiar() {
  this.busqueda.set('');
  this.buscar();
}

  private async cargar(offset: number) {
    this.cargando.set(true);
    this.error.set('');
    try {
      const r = await this.servicio.listar(offset, this.LIMITE, this.busqueda().trim());
      this.productos.update((lista) => [...lista, ...r.data]);
      this.total.set(r.pagination.total);
    } catch {
      this.error.set('No se pudieron cargar los productos.');
    } finally {
      this.cargando.set(false);
    }
  }
}