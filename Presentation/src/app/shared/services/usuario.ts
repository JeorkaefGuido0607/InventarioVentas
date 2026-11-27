
import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { environment } from '../../../environments/environment.development';
import { Rol, Usuario, UsuariosResponse } from '../models/interfaces';

@Injectable({
  providedIn: 'root'
})
export class UsuarioService {
  private readonly http = inject(HttpClient);
  private readonly API_URL = environment.servidor + '/usuarios';

  public listar(
    offset: number = 0,
    limit: number = 10,
    filtros: {
      nombre?: string;
      username?: string;
      rol_id?: number;
      activo?: boolean;
    } = {}
  ) {
    return this.http.get<UsuariosResponse>(
      `${this.API_URL}/filter/${offset}/${limit}`,
      { params: filtros }
    );
  }

  public obtener(id: number) {
    return this.http.get<{ data: Usuario }>(
      `${this.API_URL}/${id}`
    );
  }

  public obtenerRoles() {
    return this.http.get<{ data: Rol[] }>(
      `${environment.servidor}/roles`
    );
  }

  public crear(usuario: Partial<Usuario> & { password: string }) {
    return this.http.post<{ data: Usuario }>(
      this.API_URL,
      usuario
    );
  }

  public actualizar(id: number, datos: Partial<Usuario> & { password?: string }) {
    return this.http.put<{ data: Usuario }>(
      `${this.API_URL}/${id}`,
      datos
    );
  }

  public eliminar(id: number) {
    return this.http.delete<{ data: unknown }>(
      `${this.API_URL}/${id}`
    );
  }
}
