import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.angelica.inventarioventas',
  appName: 'Inventario Ventas',
  webDir: 'dist/Mobile/browser',
  server: {
    androidScheme: 'http',
    cleartext: true,
  },
};

export default config;