import { useMutation, useQueryClient } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useState } from 'react';

import type { ApiError } from '@/api/client';
import { Button, ErrorBox, Field, Screen, Title } from '@/components/ui';
import { useSession } from '@/state/session';

/** Новый адрес доставки организации. Для металла важно указать условия подъезда и разгрузки. */
export default function AddressNewScreen() {
  const { api } = useSession();
  const queryClient = useQueryClient();
  const [form, setForm] = useState({ label: '', address: '', contact_name: '', contact_phone: '', access_notes: '' });
  const set = (patch: Partial<typeof form>) => setForm({ ...form, ...patch });

  const save = useMutation({
    mutationFn: () => api('POST', 'addresses', form),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['addresses'] });
      router.back();
    },
  });
  const errors = (save.error as ApiError | null)?.fields ?? {};

  return (
    <Screen footer={<Button title="Сохранить адрес" loading={save.isPending} onPress={() => save.mutate()} />}>
      <Title>Адрес доставки</Title>
      <Field label="Название" placeholder="Стройплощадка на Ново-Садовой" value={form.label} onChangeText={(t) => set({ label: t })} />
      <Field label="Адрес" value={form.address} onChangeText={(t) => set({ address: t })} multiline error={errors.address?.[0]} />
      <Field label="Кто принимает груз" value={form.contact_name} onChangeText={(t) => set({ contact_name: t })} />
      <Field label="Телефон на месте" keyboardType="phone-pad" value={form.contact_phone} onChangeText={(t) => set({ contact_phone: t })} />
      <Field
        label="Подъезд и разгрузка"
        placeholder="Въезд для длинномера, есть кран/манипулятор не нужен, часы приёмки"
        value={form.access_notes}
        onChangeText={(t) => set({ access_notes: t })}
        multiline
      />
      {save.error ? <ErrorBox message={(save.error as Error).message} /> : null}
    </Screen>
  );
}
