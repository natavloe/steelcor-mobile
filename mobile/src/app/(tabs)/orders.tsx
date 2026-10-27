import { useQuery } from '@tanstack/react-query';
import { Link, router } from 'expo-router';
import { useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import type { Order, Paginated } from '@/api/types';
import { orderTone } from '@/components/status';
import { Badge, Button, Card, Chip, Empty, ErrorBox, Loading, Muted, Screen } from '@/components/ui';
import { Colors, Spacing } from '@/constants/theme';
import { date, money } from '@/lib/format';
import { useSession } from '@/state/session';

/** Заказы: крупный статус и блок «нужно ваше действие». */
export default function OrdersScreen() {
  const { api, token } = useSession();
  const [filter, setFilter] = useState<'active' | 'done'>('active');
  const orders = useQuery({
    queryKey: ['orders', filter],
    queryFn: () => api<Paginated<Order>>('GET', `orders?status=${filter}`),
    enabled: !!token,
  });

  if (!token) {
    return (
      <Screen>
        <Empty title="Войдите, чтобы видеть заказы">
          <Button title="Войти" onPress={() => router.push('/login')} />
        </Empty>
      </Screen>
    );
  }

  return (
    <Screen refreshing={orders.isRefetching} onRefresh={() => orders.refetch()}>
      <View style={styles.filters}>
        <Chip text="Активные" active={filter === 'active'} onPress={() => setFilter('active')} />
        <Chip text="Завершённые" active={filter === 'done'} onPress={() => setFilter('done')} />
      </View>
      {orders.isLoading ? <Loading /> : null}
      {orders.error ? <ErrorBox message={(orders.error as Error).message} onRetry={() => orders.refetch()} /> : null}
      {orders.data?.data.length === 0 ? <Empty title="Заказов пока нет" /> : null}
      {orders.data?.data.map((o) => (
        <Link key={o.id} href={{ pathname: '/order/[id]', params: { id: String(o.id) } }} asChild>
          <Pressable>
            <Card>
              <View style={styles.head}>
                <Text style={styles.number}>{o.number}</Text>
                <Muted>{date(o.created_at)}</Muted>
              </View>
              <Badge text={o.status_label} tone={orderTone(o.status)} />
              {o.action_required ? <Text style={styles.action}>⚠ {o.action_required.label}</Text> : null}
              <View style={styles.head}>
                <Muted>{o.organization.name}</Muted>
                <Text style={styles.total}>{o.total ? money(o.total) : 'сумма уточняется'}</Text>
              </View>
            </Card>
          </Pressable>
        </Link>
      ))}
    </Screen>
  );
}

const styles = StyleSheet.create({
  filters: { flexDirection: 'row', gap: Spacing.two },
  head: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  number: { fontSize: 16, fontWeight: '700', color: Colors.text },
  action: { color: Colors.warning, fontWeight: '600', fontSize: 14 },
  total: { fontSize: 15, fontWeight: '600', color: Colors.text },
});
