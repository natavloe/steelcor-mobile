import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Stack, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { Linking, StyleSheet, Text, View } from 'react-native';

import type { Order } from '@/api/types';
import { orderTone } from '@/components/status';
import { Badge, Button, Card, ErrorBox, Field, Loading, Muted, Notice, Row, Screen, Subtitle } from '@/components/ui';
import { Colors, Spacing } from '@/constants/theme';
import { date, dateTime, money, qty } from '@/lib/format';
import { useSession } from '@/state/session';

/** Заказ: статусы, условия (принять / попросить изменить), счёт, сроки, доставка. */
export default function OrderScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { api } = useSession();
  const queryClient = useQueryClient();
  const order = useQuery({ queryKey: ['order', id], queryFn: () => api<{ data: Order }>('GET', `orders/${id}`) });
  const [changes, setChanges] = useState('');
  const [showChanges, setShowChanges] = useState(false);

  const refresh = (data: { data: Order }) => {
    queryClient.setQueryData(['order', id], data);
    queryClient.invalidateQueries({ queryKey: ['orders'] });
  };
  const accept = useMutation({
    mutationFn: (offerId: number) => api<{ data: Order }>('POST', `orders/${id}/offers/${offerId}/accept`),
    onSuccess: refresh,
  });
  const requestChanges = useMutation({
    mutationFn: (offerId: number) => api<{ data: Order }>('POST', `orders/${id}/offers/${offerId}/request-changes`, { comment: changes }),
    onSuccess: (d) => {
      setShowChanges(false);
      refresh(d);
    },
  });
  const paid = useMutation({
    mutationFn: () => api<{ data: Order }>('POST', `orders/${id}/payment-notice`, { comment: 'Оплачено' }),
    onSuccess: refresh,
  });
  const cancel = useMutation({
    mutationFn: () => api<{ data: Order }>('POST', `orders/${id}/cancel`, { reason: 'Отменено клиентом в приложении' }),
    onSuccess: refresh,
  });

  if (order.isLoading) return <Loading />;
  if (order.error || !order.data) return <ErrorBox message={(order.error as Error)?.message ?? 'Заказ не найден'} onRetry={() => order.refetch()} />;
  const o = order.data.data;
  const offer = o.current_offer;
  const offerActive = o.status === 'awaiting_terms' && offer?.status === 'sent';
  const error = accept.error ?? requestChanges.error ?? paid.error ?? cancel.error;

  return (
    <Screen refreshing={order.isRefetching} onRefresh={() => order.refetch()}>
      <Stack.Screen options={{ title: o.number }} />
      <Card>
        <Badge text={o.status_label} tone={orderTone(o.status)} />
        {o.is_reagreement ? <Notice tone="warning">Условия поставки изменились — проверьте новую версию и подтвердите её.</Notice> : null}
        <Row label="Оплата" value={o.payment_status_label} />
        <Row label="Доставка" value={o.delivery_status_label} />
        <Row label="Организация" value={o.organization.name} />
        <Row label="Получение" value={o.receive_method === 'pickup' ? `Самовывоз: ${o.pickup_point?.name ?? ''}` : o.delivery_address?.address ?? 'Доставка'} />
      </Card>

      {offer ? (
        <Card>
          <Subtitle>Условия (версия {offer.version})</Subtitle>
          {offer.reason ? <Muted>Что изменилось: {offer.reason}</Muted> : null}
          {offer.lines.map((l, i) => (
            <View key={i} style={styles.line}>
              <Text style={styles.lineName}>{l.name}</Text>
              <Row label={`${qty(l.qty, l.unit)} × ${money(l.unit_price)}`} value={money(l.amount)} />
            </View>
          ))}
          <Row label="Товары" value={money(offer.totals.goods)} />
          {Number(offer.totals.delivery) > 0 ? <Row label="Доставка" value={money(offer.totals.delivery)} /> : null}
          {Number(offer.totals.services) > 0 ? <Row label="Услуги" value={money(offer.totals.services)} /> : null}
          <Row label="в т. ч. НДС" value={money(offer.totals.goods_vat)} />
          <Subtitle>Итого: {money(offer.totals.total)}</Subtitle>
          <Row label="Оплата" value={offer.payment_terms_label} />
          {offer.lead_time_text ? <Row label="Срок поставки" value={offer.lead_time_text} /> : null}
          {offerActive && offer.valid_until ? <Muted>Предложение действует до {dateTime(offer.valid_until)}</Muted> : null}
          {offerActive ? (
            <>
              <Button title="Принять условия" loading={accept.isPending} onPress={() => accept.mutate(offer.id)} />
              {showChanges ? (
                <>
                  <Field label="Что изменить?" value={changes} onChangeText={setChanges} multiline />
                  <Button title="Отправить менеджеру" kind="secondary" disabled={!changes.trim()} loading={requestChanges.isPending} onPress={() => requestChanges.mutate(offer.id)} />
                </>
              ) : (
                <Button title="Нужны изменения" kind="secondary" onPress={() => setShowChanges(true)} />
              )}
              <Muted>Принятие условий фиксирует цену и сроки. Договор подписывается отдельно — через ЭДО или на бумаге.</Muted>
            </>
          ) : null}
        </Card>
      ) : (
        <Notice>Менеджер проверяет цену, наличие и сроки. Как только предложение будет готово, придёт уведомление.</Notice>
      )}

      {o.invoices && o.invoices.length > 0 ? (
        <Card>
          <Subtitle>Счета</Subtitle>
          {o.invoices.map((inv) => (
            <View key={inv.id} style={styles.line}>
              <Row label={`№ ${inv.number}`} value={money(inv.amount)} />
              <Row label="Осталось оплатить" value={money(inv.remaining)} />
              {inv.due_date ? <Row label="Оплатить до" value={date(inv.due_date)} /> : null}
            </View>
          ))}
          <Muted>Укажите номер счёта в назначении платежа — так оплата подтвердится автоматически по выписке банка.</Muted>
          {o.status === 'awaiting_payment' && o.payment_status !== 'awaiting_confirmation' ? (
            <Button title="Я оплатил" kind="secondary" loading={paid.isPending} onPress={() => paid.mutate()} />
          ) : null}
          {o.payment_status === 'awaiting_confirmation' ? <Notice>Проверяем поступление денег по выписке банка.</Notice> : null}
        </Card>
      ) : null}

      {o.dates && Object.values(o.dates).some(Boolean) ? (
        <Card>
          <Subtitle>Сроки</Subtitle>
          {Object.entries(o.dates).map(([k, d]) =>
            d ? <Row key={k} label={d.label} value={d.to ? `${date(d.from)} – ${date(d.to)}` : date(d.from)} /> : null,
          )}
        </Card>
      ) : null}

      {o.shipments && o.shipments.length > 0 ? (
        <Card>
          <Subtitle>Доставка</Subtitle>
          {o.shipments.map((s, i) => (
            <View key={i} style={styles.line}>
              <Row label={s.carrier_label} value={s.status_label} />
              {s.planned_date ? <Row label="Плановая дата" value={date(s.planned_date)} /> : null}
              {s.cost ? <Row label={s.cost.is_confirmed ? 'Стоимость' : 'Стоимость (расчётная)'} value={money(s.cost.amount)} /> : null}
              {s.tracking_number ? <Row label="Трек-номер" value={s.tracking_number} /> : null}
              {s.tracking_url ? <Button title="Отследить" kind="secondary" onPress={() => Linking.openURL(s.tracking_url!)} /> : null}
            </View>
          ))}
        </Card>
      ) : null}

      {o.items && !offer ? (
        <Card>
          <Subtitle>Вы заказали</Subtitle>
          {o.items.map((it, i) => (
            <Row key={i} label={it.name} value={qty(it.qty, it.unit)} />
          ))}
        </Card>
      ) : null}

      {error ? <ErrorBox message={(error as Error).message} /> : null}
      {o.can_cancel ? <Button title="Отменить заказ" kind="danger" loading={cancel.isPending} onPress={() => cancel.mutate()} /> : null}
    </Screen>
  );
}

const styles = StyleSheet.create({
  line: { gap: Spacing.one, paddingVertical: Spacing.one, borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: Colors.border },
  lineName: { fontSize: 15, fontWeight: '600', color: Colors.text },
});
