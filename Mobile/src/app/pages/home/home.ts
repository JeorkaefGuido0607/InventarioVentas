import { Component, inject } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../core/auth.service';

@Component({
  selector: 'app-home',
  imports: [RouterLink],
  templateUrl: './home.html',
})
export class Home {
  auth = inject(AuthService);
  private router = inject(Router);

  async salir() {
    await this.auth.logout();
    await this.router.navigate(['/login']);
  }
}