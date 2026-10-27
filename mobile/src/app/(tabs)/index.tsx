import { useQuery } from '@tanstack/react-query';
import { Link, router } from 'expo-router';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import type { Category } from '@/api/types';
import { Card, ErrorBox, Field, Loading, Screen } from '@/components/ui';
import { Colors, Spacing } from '@/constants/theme';
import { useSession } from '@/state/session';

/** Каталог: дерево категорий и быстрый поиск. */
export default function CatalogScreen() {
  const { api } = useSession();
  const categories = useQuery({ queryKey: ['categories'], queryFn: () => api<{ data: Category[] }>('GET', 'categories') });

  return (
    <Screen refreshing={categories.isRefetching} onRefresh={() => categories.refetch()}>
      <Field
        label="Поиск по названию, марке, ГОСТу"
        placeholder="Например: круг 20 40Х"
        returnKeyType="search"
        onSubmitEditing={(e) => router.push({ pathname: '/search', params: { q: e.nativeEvent.text } })}
      />
      {categories.isLoading ? <Loading /> : null}
      {categories.error ? <ErrorBox message={(categories.error as Error).message} onRetry={() => categories.refetch()} /> : null}
      {categories.data?.data.map((root) => (
        <Card key={root.id}>
          <CategoryLink category={root} bold />
          {root.children?.map((child) => (
            <View key={child.id} style={styles.child}>
              <CategoryLink category={child} />
            </View>
          ))}
        </Card>
      ))}
    </Screen>
  );
}

function CategoryLink({ category, bold }: { category: Category; bold?: boolean }) {
  return (
    <Link href={{ pathname: '/category/[id]', params: { id: String(category.id), name: category.name } }} asChild>
      <Pressable style={styles.link}>
        <Text style={[styles.linkText, bold && styles.bold]}>{category.name}</Text>
        <Text style={styles.chevron}>›</Text>
      </Pressable>
    </Link>
  );
}

const styles = StyleSheet.create({
  link: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingVertical: Spacing.two, width: '100%' },
  linkText: { fontSize: 16, color: Colors.text },
  bold: { fontWeight: '700', fontSize: 17 },
  chevron: { fontSize: 22, color: Colors.textSecondary },
  child: { paddingLeft: Spacing.three, borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: Colors.border },
});
