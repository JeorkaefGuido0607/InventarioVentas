export interface Producto {
  id: number;
  codigo: string;
  nombre: string;
  descripcion: string | null;
  categoria_id: number;
  categoria_nombre: string;
  proveedor_id: number | null;
  proveedor_nombre: string | null;
  precio_compra: number;
  precio_venta: number;
  stock: number;
  stock_minimo: number;
  imagen_url: string | null;
  activo: boolean;
  stock_bajo: boolean;
}

export interface Paginado<T> {
  data: T[];
  pagination: { offset: number; limit: number; total: number };
}