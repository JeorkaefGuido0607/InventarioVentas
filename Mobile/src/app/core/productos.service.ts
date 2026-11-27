import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { firstValueFrom, map } from 'rxjs';
import { API_URL } from './api.config';
import { Paginado, Producto } from './modelos';

@Injectable({ providedIn: 'root' })
export class ProductosService {
  private http = inject(HttpClient);

  listar(offset: number, limit: number, nombre = ''): Promise<Paginado<Producto>> {
    const params = new HttpParams().set('nombre', nombre);
    return firstValueFrom(
      this.http.get<Paginado<Producto>>(`${API_URL}/productos/filter/${offset}/${limit}`, { params }),
    );
  }

  // Se usa con el escáner de códigos de barras
  porCodigo(codigo: string): Promise<Producto> {
    return firstValueFrom(
      this.http
        .get<{ data: Producto }>(`${API_URL}/productos/codigo/${encodeURIComponent(codigo)}`)
        .pipe(map((r) => r.data)),
    );
  }
}