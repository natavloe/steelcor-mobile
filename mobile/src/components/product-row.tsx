import { Link } from 'expo-router';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import type { Product } from '@/api/types';
import { Colors, Radius, Spacing } from '@/constants/theme';
import { money, UNIT_LABELS } from '@/lib/format';

import { Badge } from './ui';

/** Строка позиции в списке каталога. */
export function ProductRow({ product }: { product: Product }) {
  return (
    <Link href={{ pathname: '/product/[id]', params: { id: String(product.id) } }} asChild>
      <Pressable style={({ pressed }) => [styles.row, pressed && { opacity: 0.7 }]}>
        <View style={styles.main}>
          <Text style={styles.name}>{product.name}</Text>
          <Text style={styles.meta}>
            {[product.grade, product.gost].filter(Boolean).join(' · ')}
          </Text>
          {product.availability_label ? <Badge text={product.availability_label} tone="info" /> : null}
        </View>
        <View style={styles.price}>
          {product.price ? (
            <>
              <Text style={styles.priceText}>от {money(product.price.amount)}</Text>
              <Text style={styles.meta}>за {UNIT_LABELS[product.price.unit]}</Text>
            </>
          ) : (
            <Text style={styles.request}>Цена по запросу</Text>
          )}
        </View>
      </Pressable>
    </Link>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    gap: Spacing.three,
    backgroundColor: Colors.card,
    borderRadius: Radius.md,
    padding: Spacing.three,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Colors.border,
  },
  main: { flex: 1, gap: Spacing.one },
  name: { fontSize: 16, fontWeight: '600', color: Colors.text },
  meta: { fontSize: 13, color: Colors.textSecondary },
  price: { alignItems: 'flex-end', justifyContent: 'center' },
  priceText: { fontSize: 15, fontWeight: '700', color: Colors.text },
  request: { fontSize: 13, color: Colors.warning, fontWeight: '600' },
});
