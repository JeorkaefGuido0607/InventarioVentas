export interface LoginForm {
  username: string;
  password: string;
}
export interface LoginResponse {
  token: string;
  token_type: string;
  expires_in: number;
  user: {
    id: number;
    username: string;
    nombre_completo: string;
    rol_id: number;
    rol: string;
  };
}



export interface Usuario {
  id: number;
  username: string;
  nombre_completo: string;
  correo: string;
  rol_id: number;
  rol: string;
  activo: boolean;
  creado_en: string;
}

export interface Rol {
  id: number;
  nombre: string;
  descripcion: string;
}

export interface UsuariosResponse {
  data: Usuario[];
  pagination: {
    offset: number;
    limit: number;
    total: number;
  };
}
