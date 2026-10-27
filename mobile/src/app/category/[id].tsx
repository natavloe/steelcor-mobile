import { useQuery } from '@tanstack/react-query';
import { Stack, useLocalSearchParams } from 'expo-router';
import { useMemo, useState } from 'react';
import { ScrollView, StyleSheet, View } from 'react-native';

import type { CategoryFilter } from '@/api/types';
import { ProductList } from '@/components/product-list';
import { Chip, Field, Muted, Screen } from '@/components/ui';
import { Spacing } from '@/constants/theme';
import { parseNumber } from '@/lib/format';
import { useSession } from '@/state/session';

const SORTS = [
  { value: 'name', label: 'По названию' },
  { value: 'price_asc', label: 'Дешевле' },
  { value: 'price_desc', label: 'Дороже' },
];

/** Позиции категории с фильтрами по характеристикам (марка, ГОСТ, размеры) и сортировкой. */
export default function CategoryScreen() {
  const { id, name } = useLocalSearchParams<{ id: string; name?: string }>();
  const { api, region } = useSession();
  const filters = useQuery({
    queryKey: ['filters', region, id],
    queryFn: () => api<{ data: CategoryFilter[] }>('GET', `categories/${id}/filters`),
  });

  const [sort, setSort] = useState('name');
  const [values, setValues] = useState<Record<string, string>>({});
  const [ranges, setRanges] = useState<Record<string, { min?: string; max?: string }>>({});

  const params = useMemo(() => {
    const p: Record<string, string> = { category: id, sort };
    for (const [code, value] of Object.entries(values)) if (value) p[`f[${code}]`] = value;
    for (const [code, r] of Object.entries(ranges)) {
      if (r.min) p[`f[${code}][min]`] = r.min;
      if (r.max) p[`f[${code}][max]`] = r.max;
    }
    return p;
  }, [id, sort, values, ranges]);

  return (
    <Screen>
      <Stack.Screen options={{ title: name ?? 'Каталог' }} />
      <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.chips}>
        {SORTS.map((s) => (
          <Chip key={s.value} text={s.label} active={sort === s.value} onPress={() => setSort(s.value)} />
        ))}
      </ScrollView>

      {filters.data?.data.map((f) =>
        f.type === 'number' ? (
          <View key={f.code} style={styles.range}>
            <View style={styles.rangeField}>
              <Field
                label={`${f.name} от${f.unit ? `, ${f.unit}` : ''}`}
                placeholder={f.min != null ? String(f.min) : ''}
                keyboardType="decimal-pad"
                value={ranges[f.code]?.min ?? ''}
                onChangeText={(t) => setRanges({ ...ranges, [f.code]: { ...ranges[f.code], min: parseNumber(t) } })}
              />
            </View>
            <View style={styles.rangeField}>
              <Field
                label="до"
                placeholder={f.max != null ? String(f.max) : ''}
                keyboardType="decimal-pad"
                value={ranges[f.code]?.max ?? ''}
                onChangeText={(t) => setRanges({ ...ranges, [f.code]: { ...ranges[f.code], max: parseNumber(t) } })}
              />
            </View>
          </View>
        ) : f.values && f.values.length > 0 ? (
          <View key={f.code} style={styles.filter}>
            <Muted>{f.name}</Muted>
            <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.chips}>
              {f.values.map((v) => (
                <Chip
                  key={v}
                  text={v}
                  active={values[f.code] === v}
                  onPress={() => setValues({ ...values, [f.code]: values[f.code] === v ? '' : v })}
                />
              ))}
            </ScrollView>
          </View>
        ) : null,
      )}

      <ProductList params={params} />
    </Screen>
  );
}

const styles = StyleSheet.create({
  chips: { gap: Spacing.two },
  filter: { gap: Spacing.one },
  range: { flexDirection: 'row', gap: Spacing.two },
  rangeField: { flex: 1 },
});
