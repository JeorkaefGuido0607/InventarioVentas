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