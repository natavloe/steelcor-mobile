import { useMutation } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useState } from 'react';

import type { ApiError } from '@/api/client';
import type { CompanyLookupResult } from '@/api/types';
import { Button, Chip, ErrorBox, Field, Muted, Notice, Screen, Title } from '@/components/ui';
import { useSession } from '@/state/session';

type Form = {
  type: 'legal' | 'ip';
  inn: string;
  kpp: string;
  ogrn: string;
  name_full: string;
  name_short: string;
  legal_address: string;
};

const EMPTY: Form = { type: 'legal', inn: '', kpp: '', ogrn: '', name_full: '', name_short: '', legal_address: '' };

/** Добавление организации: подсказка по ИНН, затем проверка менеджером. */
export default function OrganizationNewScreen() {
  const { api, refreshUser, setOrganization } = useSession();
  const [form, setForm] = useState<Form>(EMPTY);
  const [lookupNote, setLookupNote] = useState<string | null>(null);
  const set = (patch: Partial<Form>) => setForm({ ...form, ...patch });

  const lookup = useMutation({
    mutationFn: () => api<{ data: CompanyLookupResult | null }>('GET', `organizations/lookup?inn=${form.inn}`),
    onSuccess: ({ data }) => {
      if (!data) {
        setLookupNote('Автоподсказка недоступна — заполните реквизиты вручную.');
        return;
      }
      setLookupNote('Реквизиты подставлены. Проверьте их.');
      setForm({
        ...form, type: data.type, kpp: data.kpp ?? '', ogrn: data.ogrn ?? '',
        name_full: data.name_full, name_short: data.name_short, legal_address: data.legal_address ?? '',
      });
    },
  });

  const save = useMutation({
    mutationFn: () => api<{ data: { id: number } }>('POST', 'organizations', { ...form, kpp: form.kpp || null, ogrn: form.ogrn || null }),
    onSuccess: async ({ data }) => {
      await setOrganization(data.id);
      await refreshUser();
      router.back();
    },
  });
  const fieldErrors = (save.error as ApiError | null)?.fields ?? {};

  return (
    <Screen footer={<Button title="Сохранить" loading={save.isPending} onPress={() => save.mutate()} />}>
      <Title>Организация</Title>
      <Muted>Заказы, счета и договоры оформляются на организацию. Менеджер проверит реквизиты перед выставлением счёта.</Muted>
      <Field label="ИНН" keyboardType="number-pad" value={form.inn} onChangeText={(t) => set({ inn: t.replace(/\D/g, '') })} maxLength={12} error={fieldErrors.inn?.[0]} />
      <Button title="Найти по ИНН" kind="secondary" loading={lookup.isPending} disabled={![10, 12].includes(form.inn.length)} onPress={() => lookup.mutate()} />
      {lookupNote ? <Notice>{lookupNote}</Notice> : null}
      <Muted>Тип</Muted>
      <Chip text="Юрлицо" active={form.type === 'legal'} onPress={() => set({ type: 'legal' })} />
      <Chip text="ИП" active={form.type === 'ip'} onPress={() => set({ type: 'ip' })} />
      <Field label="Полное наименование" value={form.name_full} onChangeText={(t) => set({ name_full: t })} error={fieldErrors.name_full?.[0]} />
      <Field label="Краткое наименование" value={form.name_short} onChangeText={(t) => set({ name_short: t })} error={fieldErrors.name_short?.[0]} />
      {form.type === 'legal' ? <Field label="КПП" keyboardType="number-pad" value={form.kpp} onChangeText={(t) => set({ kpp: t })} error={fieldErrors.kpp?.[0]} /> : null}
      <Field label={form.type === 'ip' ? 'ОГРНИП' : 'ОГРН'} keyboardType="number-pad" value={form.ogrn} onChangeText={(t) => set({ ogrn: t })} error={fieldErrors.ogrn?.[0]} />
      <Field label="Юридический адрес" value={form.legal_address} onChangeText={(t) => set({ legal_address: t })} multiline />
      {save.error ? <ErrorBox message={(save.error as Error).message} /> : null}
    </Screen>
  );
}
