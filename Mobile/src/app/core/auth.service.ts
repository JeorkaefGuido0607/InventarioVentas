import { Injectable, inject, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Preferences } from '@capacitor/preferences';
import { firstValueFrom } from 'rxjs';
import { API_URL } from './api.config';

export interface Usuario {
  id: number;
  username: string;
  nombre_completo: string;
  rol_id: number;
  rol: 'Admin' | 'Vendedor' | 'Bodeguero';
}

interface LoginRespuesta {
  token: string;
  token_type: string;
  expires_in: number;
  user: Usuario;
}

const CLAVE_TOKEN = 'token';
const CLAVE_USUARIO = 'usuario';
const CLAVE_EXPIRA = 'expira';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private http = inject(HttpClient);

  // Copia en memoria de la sesión: así el resto de la app la lee al instante
  readonly token = signal<string | null>(null);
  readonly usuario = signal<Usuario | null>(null);

  // Se llama al abrir la app: recupera la sesión guardada si no ha vencido
  async cargarSesion(): Promise<void> {
    const { value: token } = await Preferences.get({ key: CLAVE_TOKEN });
    const { value: usuario } = await Preferences.get({ key: CLAVE_USUARIO });
    const { value: expira } = await Preferences.get({ key: CLAVE_EXPIRA });

    if (token && usuario && expira && Date.now() < Number(expira)) {
      this.token.set(token);
      this.usuario.set(JSON.parse(usuario));
    } else {
      await this.logout();
    }
  }

  async login(username: string, password: string): Promise<void> {
    const r = await firstValueFrom(
      this.http.post<LoginRespuesta>(`${API_URL}/auth/login`, { username, password }),
    );

    await Preferences.set({ key: CLAVE_TOKEN, value: r.token });
    await Preferences.set({ key: CLAVE_USUARIO, value: JSON.stringify(r.user) });
    await Preferences.set({ key: CLAVE_EXPIRA, value: String(Date.now() + r.expires_in * 1000) });

    this.token.set(r.token);
    this.usuario.set(r.user);
  }

  async logout(): Promise<void> {
    await Preferences.remove({ key: CLAVE_TOKEN });
    await Preferences.remove({ key: CLAVE_USUARIO });
    await Preferences.remove({ key: CLAVE_EXPIRA });

    this.token.set(null);
    this.usuario.set(null);
  }
}