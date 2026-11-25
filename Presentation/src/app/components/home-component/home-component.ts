
import { Component, inject } from '@angular/core';
import { TokenService } from '../../shared/services/token-service';

@Component({
  imports: [],
  selector: 'app-home-component',
  styleUrl: './home-component.css',
  templateUrl: './home-component.html',
})
export class HomeComponent {
  private readonly srvTokens = inject(TokenService);

  public usuarioActual = this.srvTokens.decodeToken() ?? {};
}
