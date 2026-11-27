import { Capacitor } from '@capacitor/core';

export const API_URL = Capacitor.isNativePlatform()
  ? 'http://10.0.2.2:9090/api'
  : 'http://localhost:9090/api';