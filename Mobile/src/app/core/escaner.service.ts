import { Injectable } from '@angular/core';
import { Capacitor } from '@capacitor/core';
import { BarcodeFormat, BarcodeScanner } from '@capacitor-mlkit/barcode-scanning';

@Injectable({ providedIn: 'root' })
export class EscanerService {
  // La cámara solo existe en la app de Android, en el navegador no se puede escanear
  get disponible(): boolean {
    return Capacitor.isNativePlatform();
  }

  // Abre la cámara y devuelve el código leído o null si no se leyó nada
  async escanear(): Promise<string | null> {
    const { supported } = await BarcodeScanner.isSupported();
    if (!supported) {
      throw new Error('Este dispositivo no tiene una cámara compatible.');
    }

    const { available } = await BarcodeScanner.isGoogleBarcodeScannerModuleAvailable();
    if (!available) {
      await BarcodeScanner.installGoogleBarcodeScannerModule();
      throw new Error('Preparando el escáner. Intenta de nuevo en unos segundos.');
    }

    const { barcodes } = await BarcodeScanner.scan({
      formats: [BarcodeFormat.Ean13, BarcodeFormat.Ean8, BarcodeFormat.Code128, BarcodeFormat.QrCode],
    });
    return barcodes[0]?.rawValue ?? null;
  }
}