import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { TokenService } from '../../services/token-service';

export const jwtInterceptor: HttpInterceptorFn = (req, next) => {
  const srvTokens = inject(TokenService);
  const token = srvTokens.token;

  if (token) {
    const reqClonada = req.clone({
      setHeaders: {
        Authorization: `Bearer ${token}`
      }
    });

    return next(reqClonada);
  }

  return next(req);
};

