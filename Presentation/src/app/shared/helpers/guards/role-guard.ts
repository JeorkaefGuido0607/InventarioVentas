import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../../services/auth-service';
import { TokenService } from '../../services/token-service';

export const roleGuard: CanActivateFn = (route, state) => {
  const srvAuth = inject(AuthService);
  const srvTokens = inject(TokenService);
  const router = inject(Router);

  if (!srvAuth.isLoggedIn()) {
    return router.createUrlTree(['/login']);
  }

  const rolesPermitidos: string[] = route.data['roles'] ?? [];
  const token = srvTokens.decodeToken();
  const rolUsuario = token?.rol;

  if (rolesPermitidos.includes(rolUsuario)) {
    return true;
  }

  return router.createUrlTree(['/home']);
};
