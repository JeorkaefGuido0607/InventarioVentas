import { Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { TokenService } from '../../shared/services/token-service';
import { AuthService } from '../../shared/services/auth-service';

@Component({
  imports: [RouterLink],
  selector: 'app-home-component',
  styleUrl: './home-component.css',
  templateUrl: './home-component.html',
})
export class HomeComponent {
  private readonly srvTokens = inject(TokenService);
  private readonly srvAuth = inject(AuthService);

  public usuarioActual = this.srvTokens.decodeToken() ?? {};

  public cerrarSesion(): void {
    this.srvAuth.logout();
  }
}
