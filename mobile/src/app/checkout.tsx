import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useRef, useState } from 'react';

import { idempotencyKey } from '@/api/client';
import type { Address, Cart, Order, PickupPoint } from '@/api/types';
import { Button, Card, Chip, ErrorBox, Field, Loading, Muted, Notice, Row, Screen, Subtitle } from '@/components/ui';
import { money } from '@/lib/format';
import { useSession } from '@/state/session';

/** Оформление на одном экране, как в Яндекс Еде: получение, срок, контакт → «Отправить заказ». */
export default function CheckoutScreen() {
  const { api, region, organizationId, user } = useSession();
  const queryClient = useQueryClient();
  const key = useRef(idempotencyKey()).current;

  const cart = useQuery({ queryKey: ['cart', region, organizationId], queryFn: () => api<{ data: Cart }>('GET', 'cart') });
  const addresses = useQuery({ queryKey: ['addresses', organizationId], queryFn: () => api<{ data: Address[] }>('GET', 'addresses') });
  const points = useQuery({ queryKey: ['pickup-points', region], queryFn: () => api<{ data: PickupPoint[] }>('GET', 'pickup-points') });

  const [method, setMethod] = useState<'delivery' | 'pickup'>('delivery');
  const [addressId, setAddressId] = useState<number | null>(null);
  const [pointId, setPointId] = useState<number | null>(null);
  const [dateFrom, setDateFrom] = useState('');
  const [slot, setSlot] = useState('');
  const [contactName, setContactName] = useState(user?.name ?? '');
  const [contactPhone, setContactPhone] = useState(user?.phone ?? '');
  const [comment, setComment] = useState('');

  const place = useMutation({
    mutationFn: () =>
      api<{ data: Order }>('POST', 'orders', {
        receive_method: method,
        delivery_address_id: method === 'delivery' ? addressId : null,
        pickup_point_id: method === 'pickup' ? pointId : null,
        desired_date_from: toIsoDate(dateFrom),
        desired_time_slot: slot || null,
        contact_name: contactName || null,
        contact_phone: contactPhone || null,
        client_comment: comment || null,
      }, { 'Idempotency-Key': key }),
    onSuccess: ({ data }) => {
      queryClient.invalidateQueries();
      router.replace({ pathname: '/order/[id]', params: { id: String(data.id) } });
    },
  });

  if (cart.isLoading) return <Loading />;
  const data = cart.data?.data;

  return (
    <Screen
      footer={
        <Button
          title="Отправить заказ"
          loading={place.isPending}
          disabled={method === 'delivery' ? !addressId : !pointId}
          onPress={() => place.mutate()}
        />
      }>
      <Card>
        <Subtitle>Получение</Subtitle>
        <Chip text="Доставка" active={method === 'delivery'} onPress={() => setMethod('delivery')} />
        <Chip text="Самовывоз" active={method === 'pickup'} onPress={() => setMethod('pickup')} />
        {method === 'delivery' ? (
          <>
            {addresses.data?.data.map((a) => (
              <Chip key={a.id} text={a.label ? `${a.label}: ${a.address}` : a.address} active={addressId === a.id} onPress={() => setAddressId(a.id)} />
            ))}
            <Button title="+ Новый адрес" kind="secondary" onPress={() => router.push('/address-new')} />
            <Muted>Стоимость и способ доставки (машина компании, ТК) менеджер рассчитает с учётом веса и длины металла.</Muted>
          </>
        ) : (
          points.data?.data.map((p) => (
            <Chip key={p.id} text={`${p.name} — ${p.address}`} active={pointId === p.id} onPress={() => setPointId(p.id)} />
          ))
        )}
      </Card>

      <Card>
        <Subtitle>Когда нужно</Subtitle>
        <Field label="Желаемая дата (ДД.ММ.ГГГГ)" placeholder="15.11.2026" value={dateFrom} onChangeText={setDateFrom} keyboardType="numbers-and-punctuation" />
        <Field label="Удобный интервал" placeholder="9:00–13:00" value={slot} onChangeText={setSlot} />
        <Muted>Точный срок подтвердит менеджер после проверки наличия.</Muted>
      </Card>

      <Card>
        <Subtitle>Контакт по заказу</Subtitle>
        <Field label="Имя" value={contactName} onChangeText={setContactName} />
        <Field label="Телефон" keyboardType="phone-pad" value={contactPhone} onChangeText={setContactPhone} />
        <Field label="Комментарий" value={comment} onChangeText={setComment} multiline />
      </Card>

      {data ? (
        <Card>
          <Row label="Позиций" value={String(data.items.length)} />
          <Row label="Предварительно" value={money(data.preliminary_total)} />
        </Card>
      ) : null}
      <Notice>После отправки менеджер проверит цену, наличие и сроки и пришлёт предложение. Оплачивать ничего не нужно, пока вы не примете условия.</Notice>
      {place.error ? <ErrorBox message={(place.error as Error).message} /> : null}
    </Screen>
  );
}

function toIsoDate(text: string): string | null {
  const m = text.trim().match(/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/);
  return m ? `${m[3]}-${m[2].padStart(2, '0')}-${m[1].padStart(2, '0')}` : null;
}
