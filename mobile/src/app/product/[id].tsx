import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { router, Stack, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { StyleSheet, View } from 'react-native';

import type { Product, Unit } from '@/api/types';
import { Badge, Button, Card, Chip, ErrorBox, Field, Loading, Muted, Notice, Row, Screen, Subtitle, Title } from '@/components/ui';
import { Spacing } from '@/constants/theme';
import { money, parseNumber, qty as fmtQty, UNIT_LABELS } from '@/lib/format';
import { requireAccount } from '@/lib/require-account';
import { useDebounced } from '@/lib/use-debounced';
import { useSession } from '@/state/session';

const ATTR_LABELS: Record<string, string> = { diameter: 'Диаметр, мм', thickness: 'Толщина, мм', width: 'Ширина, мм', length: 'Длина, мм', technology: 'Технология', wall: 'Стенка, мм' };

/** Карточка позиции: характеристики, ориентировочная цена, калькулятор единиц, добавление в корзину. */
export default function ProductScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { api, region, token, organizationId } = useSession();
  const queryClient = useQueryClient();
  const product = useQuery({ queryKey: ['product', region, id], queryFn: () => api<{ data: Product }>('GET', `products/${id}`) });

  const [unit, setUnit] = useState<Unit | null>(null);
  const [amount, setAmount] = useState('');
  const [comment, setComment] = useState('');
  const debouncedAmount = useDebounced(amount);

  const p = product.data?.data;
  const activeUnit = unit ?? p?.price?.unit ?? p?.base_unit ?? 't';
  const priceUnit = p?.price?.unit;

  // Пересчёт в единицы цены (по теоретической массе) — для предварительной суммы.
  const conversion = useQuery({
    queryKey: ['convert', p?.id, debouncedAmount, activeUnit, priceUnit],
    queryFn: () => api<{ data: { qty: string } }>('GET', `products/${p!.id}/convert?qty=${debouncedAmount}&from=${activeUnit}&to=${priceUnit}`),
    enabled: !!p && !!priceUnit && !!debouncedAmount && Number(debouncedAmount) > 0 && activeUnit !== priceUnit,
    retry: false,
  });
  const converted = activeUnit !== priceUnit ? (conversion.data?.data.qty ?? null) : null;

  const add = useMutation({
    mutationFn: () => api('POST', 'cart/items', { product_id: Number(id), qty: amount, unit: activeUnit, comment: comment || null }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['cart'] });
      router.push('/cart');
    },
  });

  if (product.isLoading) return <Loading />;
  if (product.error || !p) return <ErrorBox message={(product.error as Error)?.message ?? 'Позиция не найдена'} />;

  const qtyInPriceUnit = p.price ? (activeUnit === p.price.unit ? amount : converted) : null;
  const preliminary = p.price && qtyInPriceUnit ? Number(qtyInPriceUnit) * Number(p.price.amount) : null;

  return (
    <Screen
      footer={
        <Button
          title="В корзину"
          disabled={!amount || Number(amount) <= 0}
          loading={add.isPending}
          onPress={() => requireAccount(token, organizationId) && add.mutate()}
        />
      }>
      <Stack.Screen options={{ title: p.grade ?? 'Позиция' }} />
      <Title>{p.name}</Title>
      {p.availability_label ? <Badge text={p.availability_label} tone="info" /> : null}

      <Card>
        {p.price ? (
          <>
            <Subtitle>от {money(p.price.amount)} за {UNIT_LABELS[p.price.unit]}</Subtitle>
            <Muted>
              Цена предварительная{p.price.vat_included ? ', с НДС' : ''}
              {p.price.valid_until ? `, актуальна до ${new Date(p.price.valid_until).toLocaleDateString('ru-RU')}` : ''}. Окончательную подтвердит менеджер.
            </Muted>
          </>
        ) : (
          <>
            <Subtitle>Цена по запросу</Subtitle>
            <Muted>Добавьте позицию в корзину и оформите заказ — менеджер пришлёт предложение с ценой и сроком.</Muted>
          </>
        )}
        {p.lead_time_days ? <Row label="Ориентировочный срок" value={`${p.lead_time_days.min ?? ''}–${p.lead_time_days.max} дн.`} /> : null}
      </Card>

      <Card>
        <Subtitle>Характеристики</Subtitle>
        {p.grade ? <Row label="Марка" value={p.grade} /> : null}
        {p.gost ? <Row label="ГОСТ / ТУ" value={p.gost} /> : null}
        {Object.entries(p.attrs)
          .filter(([k]) => !['grade', 'gost'].includes(k))
          .map(([k, v]) => (
            <Row key={k} label={ATTR_LABELS[k] ?? k} value={String(v)} />
          ))}
        {p.weight_per_m ? <Row label="Масса 1 м (теор.)" value={`${p.weight_per_m} кг`} /> : null}
        {p.std_length_mm ? <Row label="Длина" value={`${p.std_length_mm / 1000} м`} /> : null}
        {p.is_oversize ? <Notice tone="warning">Длинномерный груз: доставка машиной компании или самовывоз.</Notice> : null}
      </Card>

      <Card>
        <Subtitle>Сколько нужно</Subtitle>
        <View style={styles.units}>
          {p.units.map((u) => (
            <Chip key={u} text={UNIT_LABELS[u]} active={activeUnit === u} onPress={() => setUnit(u)} />
          ))}
        </View>
        <Field
          label={`Количество, ${UNIT_LABELS[activeUnit]}`}
          keyboardType="decimal-pad"
          value={amount}
          onChangeText={(t) => setAmount(parseNumber(t))}
          placeholder="0"
        />
        {converted && p.price ? <Muted>≈ {fmtQty(converted, p.price.unit)} по теоретической массе</Muted> : null}
        {preliminary ? <Row label="Предварительно" value={money(preliminary)} /> : null}
        <Field label="Комментарий (резка, длина и т. п.)" value={comment} onChangeText={setComment} placeholder="Порезать по 3 м" />
        {add.error ? <ErrorBox message={(add.error as Error).message} /> : null}
      </Card>
    </Screen>
  );
}

const styles = StyleSheet.create({ units: { flexDirection: 'row', gap: Spacing.two, flexWrap: 'wrap' } });
