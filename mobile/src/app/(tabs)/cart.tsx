import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { router } from 'expo-router';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import type { Cart } from '@/api/types';
import { Button, Card, Empty, ErrorBox, Loading, Muted, Notice, Row, Screen, Subtitle } from '@/components/ui';
import { Colors, Spacing } from '@/constants/theme';
import { money, qty } from '@/lib/format';
import { useSession } from '@/state/session';

/** Корзина: предварительный расчёт; окончательные цену и срок подтверждает менеджер. */
export default function CartScreen() {
  const { api, token, organizationId, region } = useSession();
  const queryClient = useQueryClient();
  const cart = useQuery({
    queryKey: ['cart', region, organizationId],
    queryFn: () => api<{ data: Cart }>('GET', 'cart'),
    enabled: !!token && !!organizationId,
  });
  const remove = useMutation({
    mutationFn: (itemId: number) => api('DELETE', `cart/items/${itemId}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['cart'] }),
  });

  if (!token) {
    return (
      <Screen>
        <Empty title="Войдите, чтобы оформлять заказы">
          <Button title="Войти по номеру телефона" onPress={() => router.push('/login')} />
        </Empty>
      </Screen>
    );
  }
  if (!organizationId) {
    return (
      <Screen>
        <Empty title="Добавьте организацию">
          <Muted>Заказы оформляются на юрлицо или ИП.</Muted>
          <Button title="Добавить организацию" onPress={() => router.push('/organization-new')} />
        </Empty>
      </Screen>
    );
  }
  if (cart.isLoading) return <Loading />;
  if (cart.error) return <ErrorBox message={(cart.error as Error).message} onRetry={() => cart.refetch()} />;

  const data = cart.data!.data;
  if (data.items.length === 0) {
    return (
      <Screen>
        <Empty title="Корзина пуста">
          <Button title="Перейти в каталог" kind="secondary" onPress={() => router.push('/')} />
        </Empty>
      </Screen>
    );
  }

  return (
    <Screen
      refreshing={cart.isRefetching}
      onRefresh={() => cart.refetch()}
      footer={<Button title="Оформить заказ" onPress={() => router.push('/checkout')} />}>
      {data.items.map((item) => (
        <Card key={item.id}>
          <View style={styles.head}>
            <Text style={styles.name}>{item.product.name}</Text>
            <Pressable onPress={() => remove.mutate(item.id)} hitSlop={10}>
              <Text style={styles.remove}>Удалить</Text>
            </Pressable>
          </View>
          <Row label="Количество" value={qty(item.qty, item.unit)} />
          <Row label="Предварительно" value={item.preliminary_amount ? money(item.preliminary_amount) : 'цена по запросу'} />
          {item.comment ? <Muted>{item.comment}</Muted> : null}
        </Card>
      ))}
      <Card>
        <Subtitle>Итого предварительно: {money(data.preliminary_total)}</Subtitle>
        {!data.total_is_complete ? <Muted>Без учёта позиций с ценой по запросу.</Muted> : null}
        <Row label="Теоретический вес" value={`${Number(data.theoretical_weight_kg).toLocaleString('ru-RU')} кг`} />
      </Card>
      <Notice>{data.note}</Notice>
    </Screen>
  );
}

const styles = StyleSheet.create({
  head: { flexDirection: 'row', justifyContent: 'space-between', gap: Spacing.three },
  name: { flex: 1, fontSize: 16, fontWeight: '600', color: Colors.text },
  remove: { color: Colors.danger, fontSize: 14 },
});
