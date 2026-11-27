import { Component, inject } from '@angular/core';
import {
  FormBuilder,
  FormGroup,
  Validators,
  ReactiveFormsModule
} from '@angular/forms';
import { MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { Categoria } from '../../../shared/models/interfaces';

@Component({
  imports: [ReactiveFormsModule],
  selector: 'app-frm-categoria',
  styleUrl: './frm-categoria.css',
  templateUrl: './frm-categoria.html',
})
export class FrmCategoria {
  private readonly fb = inject(FormBuilder);
  private readonly dialogRef = inject(MatDialogRef<FrmCategoria>);
  public categoria = inject<Categoria | null>(MAT_DIALOG_DATA, { optional: true });

  public formulario: FormGroup = this.fb.group({
    nombre: [
      this.categoria?.nombre ?? '',
      [
        Validators.required,
        Validators.minLength(3),
        Validators.maxLength(100),
        Validators.pattern(/^[\p{L}0-9][\p{L}0-9 ,.&()-]*$/u)
      ]
    ],
    descripcion: [
      this.categoria?.descripcion ?? '',
      [Validators.maxLength(255)]
    ],
    activo: [this.categoria?.activo ?? true]
  });

  public guardar(): void {
    if (this.formulario.invalid) {
      this.formulario.markAllAsTouched();
      return;
    }

    this.dialogRef.close(this.formulario.value);
  }

  public cancelar(): void {
    this.dialogRef.close();
  }
}