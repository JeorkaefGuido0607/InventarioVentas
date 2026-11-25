import { Routes } from '@angular/router';
import { Login } from './pages/login/login';
import { Home } from './pages/home/home';
import { authGuard } from './core/auth.guard';
import { Productos } from './pages/productos/productos';

export const routes: Routes = [
  { path: 'login', component: Login },
  { path: 'inicio', component: Home, canActivate: [authGuard] },
  { path: 'productos', component: Productos, canActivate: [authGuard] },
  { path: '', redirectTo: 'login', pathMatch: 'full' },
  { path: '**', redirectTo: 'login' },
  
];