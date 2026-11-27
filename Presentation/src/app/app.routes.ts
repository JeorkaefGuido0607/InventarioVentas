import { Routes } from '@angular/router';
import { LoginComponent } from './components/login-component/login-component/login-component';
import { HomeComponent } from './components/home-component/home-component';
import { authGuard } from './shared/helpers/guards/auth-guard';
import { roleGuard } from './shared/helpers/guards/role-guard';
import { AdminComponent } from './components/admin-component/admin-component';
import { VendedorComponent } from './components/vendedor-component/vendedor-component';
import { BodegueroComponent } from './components/bodeguero-component/bodeguero-component';
import { CategoriaComponent } from './components/categoria-component/categoria-component';

export const routes: Routes = [{
  path: 'login',
  component: LoginComponent
},

{
  path: 'home',
  component: HomeComponent,
  canActivate: [authGuard, roleGuard],
  data: {
    roles: ['Admin', 'Vendedor', 'Bodeguero']
  }
},

{
  path: 'admin',
  component: AdminComponent,
  canActivate: [authGuard, roleGuard],
  data: { roles: ['Admin'] }
},

{
  path: 'vendedor',
  component: VendedorComponent,
  canActivate: [authGuard, roleGuard],
  data: { roles: ['Vendedor'] }
},

{
  path: 'bodeguero',
  component: BodegueroComponent,
  canActivate: [authGuard, roleGuard],
  data: { roles: ['Bodeguero'] }
},

{
  path: '',
  redirectTo: 'login',
  pathMatch: 'full'
}, {
  path: 'categorias',
  component: CategoriaComponent,
  canActivate: [authGuard, roleGuard],
  data: {
    roles: ['Admin', 'Vendedor', 'Bodeguero']
  }
},
];