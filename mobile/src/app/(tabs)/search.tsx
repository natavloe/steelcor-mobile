import { useLocalSearchParams } from 'expo-router';
import { useState } from 'react';

import { ProductList } from '@/components/product-list';
import { Field, Muted, Screen } from '@/components/ui';

/** Поиск по всему каталогу региона: название, марка стали, ГОСТ, размеры. */
export default function SearchScreen() {
  const { q } = useLocalSearchParams<{ q?: string }>();
  // Новый запрос из каталога — новое состояние экрана.
  return <Search key={q ?? ''} initial={q ?? ''} />;
}

function Search({ initial }: { initial: string }) {
  const [text, setText] = useState(initial);
  const [query, setQuery] = useState(initial);

  return (
    <Screen>
      <Field
        label="Что ищем?"
        placeholder="круг 20 40Х, лист 4 Ст3, ГОСТ 2590"
        value={text}
        onChangeText={setText}
        onSubmitEditing={() => setQuery(text.trim())}
        returnKeyType="search"
        autoFocus={!initial}
      />
      {query.length >= 2 ? <ProductList params={{ q: query }} /> : <Muted>Введите хотя бы 2 символа и нажмите «Поиск» на клавиатуре.</Muted>}
    </Screen>
  );
}
