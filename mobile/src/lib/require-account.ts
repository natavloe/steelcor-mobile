import { router } from 'expo-router';

/** Перед действиями с корзиной и заказами: нужен вход и выбранная организация. */
export function requireAccount(token: string | null, organizationId: number | null): boolean {
  if (!token) {
    router.push('/login');
    return false;
  }
  if (!organizationId) {
    router.push('/organization-new');
    return false;
  }
  return true;
}
