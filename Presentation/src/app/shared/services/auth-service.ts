import { HttpClient } from '@angular/common/http';
import { Injectable, inject, signal} from '@angular/core';
import { LoginForm, LoginResponse } from '../models/interfaces';
import { environment } from '../../../environments/environment.development';
import { TokenService } from './token-service';
import { UserModel } from '../models/user-model';
import { Router } from '@angular/router';

@Injectable({
  providedIn: 'root'
})
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly API_URL = environment.servidor + '/auth';
  private readonly srvTokens = inject(TokenService);
  private readonly router = inject(Router);
  public userActualS = signal(new UserModel());

  public login(datos: LoginForm) {
    return this.http.post<LoginResponse>(
      `${this.API_URL}/login`, datos
    );
  }

  public doLogin(respuesta: LoginResponse): void {
    this.srvTokens.setToken(respuesta.token);
    this.userActualS.set(new UserModel(respuesta.user));
  }

  public isLoggedIn(): boolean {
    return !!this.srvTokens.token && !this.srvTokens.jwtTokenExp();
  }

  public doLogOut(): void {
    this.srvTokens.removeTokens();
    this.userActualS.set(new UserModel());
  }

  public logout(): void {
    this.doLogOut();
    this.router.navigate(['/login']);
  }
}