import { Injectable } from '@angular/core';
import { JwtHelperService } from '@auth0/angular-jwt';

@Injectable({
  providedIn: 'root'
})
export class TokenService {
    private readonly JWT_TOKEN = 'JWT_TOKEN';

    public setToken(token: string): void {
        localStorage.setItem(this.JWT_TOKEN, token);
    }

    public get token(): string | null {
        return localStorage.getItem(this.JWT_TOKEN);
    }

    public removeTokens(): void {
        localStorage.removeItem(this.JWT_TOKEN);
    }

    public decodeToken(): any {
        const helper = new JwtHelperService();
        return helper.decodeToken(this.token ?? '');
    }

    public jwtTokenExp(): boolean {
        const helper = new JwtHelperService();
        return helper.isTokenExpired(this.token ?? '');
    }
}
