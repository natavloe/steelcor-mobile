import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Stack } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import { useEffect, useState } from 'react';

import { Colors } from '@/constants/theme';
import { SessionProvider, useSession } from '@/state/session';

SplashScreen.preventAutoHideAsync();

function RootStack() {
  const { ready } = useSession();

  useEffect(() => {
    if (ready) SplashScreen.hideAsync();
  }, [ready]);

  if (!ready) return null;

  return (
    <Stack
      screenOptions={{
        headerStyle: { backgroundColor: Colors.card },
        headerTintColor: Colors.primary,
        headerTitleStyle: { color: Colors.text },
        headerBackTitle: 'Назад',
        contentStyle: { backgroundColor: Colors.background },
      }}>
      <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
      <Stack.Screen name="region" options={{ title: 'Город', presentation: 'modal' }} />
      <Stack.Screen name="login" options={{ title: 'Вход', presentation: 'modal' }} />
      <Stack.Screen name="organization-new" options={{ title: 'Организация' }} />
      <Stack.Screen name="address-new" options={{ title: 'Новый адрес' }} />
      <Stack.Screen name="checkout" options={{ title: 'Оформление заказа' }} />
      <Stack.Screen name="category/[id]" options={{ title: 'Каталог' }} />
      <Stack.Screen name="product/[id]" options={{ title: 'Позиция' }} />
      <Stack.Screen name="order/[id]" options={{ title: 'Заказ' }} />
    </Stack>
  );
}

export default function RootLayout() {
  const [queryClient] = useState(
    () => new QueryClient({ defaultOptions: { queries: { retry: 1, staleTime: 30_000 } } }),
  );

  return (
    <QueryClientProvider client={queryClient}>
      <SessionProvider>
        <StatusBar style="dark" />
        <RootStack />
      </SessionProvider>
    </QueryClientProvider>
  );
}
