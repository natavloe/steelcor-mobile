import { useQuery } from '@tanstack/react-query';
import { router } from 'expo-router';

import type { Region } from '@/api/types';
import { Button, ErrorBox, Loading, Muted, Screen, Title } from '@/components/ui';
import { useSession } from '@/state/session';

/** Выбор города: от него зависят цены, сроки, самовывоз и менеджер. */
export default function RegionScreen() {
  const { api, region, setRegion } = useSession();
  const regions = useQuery({ queryKey: ['regions'], queryFn: () => api<{ data: Region[] }>('GET', 'regions') });

  return (
    <Screen>
      <Title>Ваш город</Title>
      <Muted>От города зависят цены, сроки поставки и способы получения. Его можно сменить в профиле.</Muted>
      {regions.isLoading ? <Loading /> : null}
      {regions.error ? <ErrorBox message={(regions.error as Error).message} onRetry={() => regions.refetch()} /> : null}
      {regions.data?.data.map((r) => (
        <Button
          key={r.code}
          title={r.name}
          kind={r.code === region ? 'primary' : 'secondary'}
          onPress={async () => {
            await setRegion(r.code);
            if (router.canGoBack()) router.back();
            else router.replace('/');
          }}
        />
      ))}
    </Screen>
  );
}
