/**
 * Сессия: токен входа, выбранный город и организация. Хранится в защищённом хранилище.
 * Все запросы к API идут через api(), который подставляет нужные заголовки.
 */
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';

import { request } from '@/api/client';
import type { User } from '@/api/types';

import { getItem, setItem } from './storage';

interface Session {
  ready: boolean;
  token: string | null;
  region: string | null;
  organizationId: number | null;
  user: User | null;
  setRegion: (code: string) => Promise<void>;
  setOrganization: (id: number | null) => Promise<void>;
  signIn: (token: string, user: User) => Promise<void>;
  signOut: () => Promise<void>;
  refreshUser: () => Promise<unknown>;
  api: <T>(method: 'GET' | 'POST' | 'PATCH' | 'DELETE', path: string, body?: Record<string, unknown> | FormData, headers?: Record<string, string>) => Promise<T>;
}

const SessionContext = createContext<Session | null>(null);

interface Stored {
  token: string | null;
  region: string | null;
  organizationId: number | null;
}

export function SessionProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient();
  const [stored, setStored] = useState<Stored | null>(null);

  useEffect(() => {
    Promise.all([getItem('token'), getItem('region'), getItem('organization')]).then(([token, region, org]) =>
      setStored({ token, region, organizationId: org ? Number(org) : null }),
    );
  }, []);

  const token = stored?.token ?? null;
  const me = useQuery({
    queryKey: ['me', token],
    queryFn: () => request<{ data: User }>('GET', 'me', { token }),
    enabled: !!token,
  });
  const user = me.data?.data ?? null;

  // Выбранная организация, если пользователь в ней состоит; иначе — первая доступная.
  const storedOrganizationId = stored?.organizationId ?? null;
  const userOrganizationIds = user?.organizations.map((o) => o.id) ?? null;
  const organizationId =
    userOrganizationIds === null
      ? storedOrganizationId
      : storedOrganizationId !== null && userOrganizationIds.includes(storedOrganizationId)
        ? storedOrganizationId
        : (userOrganizationIds[0] ?? null);

  const region = stored?.region ?? null;

  const api = useCallback<Session['api']>(
    (method, path, body, headers) => request(method, path, { token, region, organizationId }, body, headers),
    [token, region, organizationId],
  );

  const value = useMemo<Session>(
    () => ({
      ready: stored !== null,
      token,
      region,
      organizationId,
      user,
      api,
      refreshUser: () => me.refetch(),
      setRegion: async (code) => {
        setStored((s) => ({ ...(s as Stored), region: code }));
        await setItem('region', code);
        queryClient.invalidateQueries();
      },
      setOrganization: async (id) => {
        setStored((s) => ({ ...(s as Stored), organizationId: id }));
        await setItem('organization', id === null ? null : String(id));
        queryClient.invalidateQueries();
      },
      signIn: async (newToken, newUser) => {
        queryClient.setQueryData(['me', newToken], { data: newUser });
        setStored((s) => ({ ...(s as Stored), token: newToken, organizationId: newUser.organizations[0]?.id ?? null }));
        await setItem('token', newToken);
      },
      signOut: async () => {
        try {
          await request('POST', 'auth/logout', { token });
        } catch {
          // токен мог уже истечь — всё равно выходим
        }
        setStored((s) => ({ ...(s as Stored), token: null, organizationId: null }));
        await Promise.all([setItem('token', null), setItem('organization', null)]);
        queryClient.clear();
      },
    }),
    [stored, token, region, organizationId, user, api, me, queryClient],
  );

  return <SessionContext.Provider value={value}>{children}</SessionContext.Provider>;
}

export function useSession(): Session {
  const session = useContext(SessionContext);
  if (!session) throw new Error('useSession вне SessionProvider');
  return session;
}
