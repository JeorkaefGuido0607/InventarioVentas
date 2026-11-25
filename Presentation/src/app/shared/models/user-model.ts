export class UserModel {
  id: number;
  username: string;
  nombre_completo: string;
  rol_id: number;
  rol: string;

  constructor(user?: Partial<UserModel>) {
    this.id = user?.id ?? 0;
    this.username = user?.username ?? '';
    this.nombre_completo = user?.nombre_completo ?? '';
    this.rol_id = user?.rol_id ?? 0;
    this.rol = user?.rol ?? '';
  }
}