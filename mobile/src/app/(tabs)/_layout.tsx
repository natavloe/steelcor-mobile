import Ionicons from '@expo/vector-icons/Ionicons';
import { useQuery } from '@tanstack/react-query';
import { Redirect, Tabs } from 'expo-router';
import type { ColorValue } from 'react-native';

import type { Cart } from '@/api/types';
import { Colors } from '@/constants/theme';
import { useSession } from '@/state/session';

type IconName = keyof typeof Ionicons.glyphMap;

const icon = (name: IconName) =>
  function TabIcon({ color, size }: { color: ColorValue; size: number }) {
    return <Ionicons name={name} color={typeof color === 'string' ? color : undefined} size={size} />;
  };

export default function TabsLayout() {
  const { region, token, organizationId, api } = useSession();
  const cart = useQuery({
    queryKey: ['cart', region, organizationId],
    queryFn: () => api<{ data: Cart }>('GET', 'cart'),
    enabled: !!token && !!organizationId && !!region,
  });

  // Без города каталог не показать: цены и наличие зависят от региона.
  if (!region) return <Redirect href="/region" />;

  const count = cart.data?.data.items.length ?? 0;

  return (
    <Tabs
      screenOptions={{
        tabBarActiveTintColor: Colors.primary,
        tabBarInactiveTintColor: Colors.textSecondary,
        headerStyle: { backgroundColor: Colors.card },
        headerTitleStyle: { color: Colors.text },
        sceneStyle: { backgroundColor: Colors.background },
      }}>
      <Tabs.Screen name="index" options={{ title: 'Каталог', tabBarIcon: icon('grid-outline') }} />
      <Tabs.Screen name="search" options={{ title: 'Поиск', tabBarIcon: icon('search-outline') }} />
      <Tabs.Screen
        name="cart"
        options={{ title: 'Корзина', tabBarIcon: icon('cart-outline'), tabBarBadge: count > 0 ? count : undefined }}
      />
      <Tabs.Screen name="orders" options={{ title: 'Заказы', tabBarIcon: icon('receipt-outline') }} />
      <Tabs.Screen name="profile" options={{ title: 'Профиль', tabBarIcon: icon('person-outline') }} />
    </Tabs>
  );
}
