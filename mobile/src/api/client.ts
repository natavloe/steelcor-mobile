/**
 * Клиент API сервера «Стилкор». Все ключи интеграций — только на сервере;
 * приложение знает лишь адрес API и свой токен входа.
 */
import Constants from 'expo-constants';
import { Platform } from 'react-native';

const DEFAULT_URL = Platform.OS === 'android' ? 'http://10.0.2.2:8000' : 'http://127.0.0.1:8000';

export const API_URL: string =
  process.env.EXPO_PUBLIC_API_URL ?? (Constants.expoConfig?.extra?.apiUrl as string | undefined) ?? DEFAULT_URL;

/** Ошибка API с машинным кодом и понятным текстом для человека. */
export class ApiError extends Error {
  constructor(
    public readonly code: string,
    message: string,
    public readonly status: number,
    public readonly fields: Record<string, string[]> = {},
  ) {
    super(message);
  }
}

export interface RequestContext {
  token?: string | null;
  region?: string | null;
  organizationId?: number | null;
}

type Body = Record<string, unknown> | FormData | undefined;

export async function request<T>(
  method: 'GET' | 'POST' | 'PATCH' | 'DELETE',
  path: string,
  ctx: RequestContext,
  body?: Body,
  extraHeaders: Record<string, string> = {},
): Promise<T> {
  const headers: Record<string, string> = { Accept: 'application/json', ...extraHeaders };
  if (ctx.token) headers.Authorization = `Bearer ${ctx.token}`;
  if (ctx.region) headers['X-Region'] = ctx.region;
  if (ctx.organizationId) headers['X-Organization'] = String(ctx.organizationId);

  let payload: BodyInit | undefined;
  if (body instanceof FormData) {
    payload = body;
  } else if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
    payload = JSON.stringify(body);
  }

  let response: Response;
  try {
    response = await fetch(`${API_URL}/api/client/v1/${path.replace(/^\//, '')}`, { method, headers, body: payload });
  } catch {
    throw new ApiError('network', 'Нет связи с сервером. Проверьте интернет и попробуйте ещё раз.', 0);
  }

  const json = await response.json().catch(() => null);
  if (!response.ok) {
    const error = json?.error;
    throw new ApiError(
      error?.code ?? 'http_' + response.status,
      error?.message ?? 'Что-то пошло не так. Попробуйте позже.',
      response.status,
      error?.fields ?? {},
    );
  }

  return json as T;
}

/** Ключ повтора для оформления заказа: повторная отправка при плохой связи не создаст второй заказ. */
export function idempotencyKey(): string {
  return `ord-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
}
