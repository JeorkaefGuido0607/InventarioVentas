import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../../services/auth-service';

export const authGuard: CanActivateFn = (route, state) => {
  const srvAuth = inject(AuthService);
  const router = inject(Router);


  if (srvAuth.isLoggedIn()) {
    console.log('AuthGuard: acceso permitido');
    return true;
  }

  return router.createUrlTree(['/login']);
};