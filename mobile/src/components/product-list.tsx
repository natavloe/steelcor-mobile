import { useInfiniteQuery } from '@tanstack/react-query';
import { StyleSheet, View } from 'react-native';

import type { Paginated, Product } from '@/api/types';
import { Spacing } from '@/constants/theme';
import { useSession } from '@/state/session';

import { ProductRow } from './product-row';
import { Button, Empty, ErrorBox, Loading } from './ui';

/** Список позиций с подгрузкой страниц. params — параметры запроса /products. */
export function ProductList({ params }: { params: Record<string, string> }) {
  const { api, region } = useSession();
  const query = new URLSearchParams(params).toString();
  const products = useInfiniteQuery({
    queryKey: ['products', region, query],
    initialPageParam: 1,
    queryFn: ({ pageParam }) => api<Paginated<Product>>('GET', `products?${query}&page=${pageParam}`),
    getNextPageParam: (last) => (last.meta && last.meta.current_page < last.meta.last_page ? last.meta.current_page + 1 : undefined),
  });

  if (products.isLoading) return <Loading />;
  if (products.error) return <ErrorBox message={(products.error as Error).message} onRetry={() => products.refetch()} />;

  const items = products.data?.pages.flatMap((p) => p.data) ?? [];
  if (items.length === 0) return <Empty title="Ничего не найдено" />;

  return (
    <View style={styles.list}>
      {items.map((p) => (
        <ProductRow key={p.id} product={p} />
      ))}
      {products.hasNextPage ? (
        <Button title="Показать ещё" kind="secondary" loading={products.isFetchingNextPage} onPress={() => products.fetchNextPage()} />
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({ list: { gap: Spacing.two } });
