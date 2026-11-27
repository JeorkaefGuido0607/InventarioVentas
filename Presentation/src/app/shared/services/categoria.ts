
import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { environment } from '../../../environments/environment.development';
import { Categoria, CategoriasResponse } from '../models/interfaces';

@Injectable({
  providedIn: 'root'
})
export class CategoriaService {

  private readonly http = inject(HttpClient);
  private readonly API_URL = environment.servidor + '/categorias';

  public listar(
    offset: number = 0,
    limit: number = 10,
    filtros: {
      nombre?: string;
      activo?: boolean;
    } = {}
  ) {
    return this.http.get<CategoriasResponse>(
      `${this.API_URL}/filter/${offset}/${limit}`,
      { params: filtros }
    );
  }

  public obtener(id: number) {
    return this.http.get<{ data: Categoria }>(
      `${this.API_URL}/${id}`
    );
  }

  public crear(categoria: Partial<Categoria>) {
    return this.http.post<{ data: Categoria }>(
      this.API_URL,
      categoria
    );
  }

  public actualizar(id: number, datos: Partial<Categoria>) {
    return this.http.put<{ data: Categoria }>(
      `${this.API_URL}/${id}`,
      datos
    );
  }

  public eliminar(id: number) {
    return this.http.delete<{ message: string }>(
      `${this.API_URL}/${id}`
    );
  }
}
