import { router } from 'expo-router';
import { useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { ApiError, request } from '@/api/client';
import type { User } from '@/api/types';
import { Button, ErrorBox, Field, Muted, Screen, Title } from '@/components/ui';
import { Colors, Spacing } from '@/constants/theme';
import { useSession } from '@/state/session';

/** Вход по номеру телефона и SMS-коду. Новому пользователю — согласие на обработку ПДн. */
export default function LoginScreen() {
  const { signIn } = useSession();
  const [phone, setPhone] = useState('+7');
  const [code, setCode] = useState('');
  const [name, setName] = useState('');
  const [step, setStep] = useState<'phone' | 'code'>('phone');
  const [isNew, setIsNew] = useState(false);
  const [consent, setConsent] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const requestCode = async () => {
    setLoading(true);
    setError(null);
    try {
      const { data } = await request<{ data: { phone: string; is_new_user: boolean } }>('POST', 'auth/otp/request', {}, { phone });
      setPhone(data.phone);
      setIsNew(data.is_new_user);
      setStep('code');
    } catch (e) {
      setError((e as ApiError).message);
    } finally {
      setLoading(false);
    }
  };

  const verify = async () => {
    setLoading(true);
    setError(null);
    try {
      const { data } = await request<{ data: { token: string; user: User } }>('POST', 'auth/otp/verify', {}, {
        phone, code, name: name || null, pd_consent: consent, device_name: 'mobile',
      });
      await signIn(data.token, data.user);
      if (data.user.organizations.length === 0) router.replace('/organization-new');
      else router.back();
    } catch (e) {
      setError((e as ApiError).message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <Screen>
      <Title>Вход</Title>
      {step === 'phone' ? (
        <>
          <Field label="Номер телефона" keyboardType="phone-pad" value={phone} onChangeText={setPhone} autoFocus />
          <Button title="Получить код" loading={loading} onPress={requestCode} disabled={phone.replace(/\D/g, '').length < 11} />
        </>
      ) : (
        <>
          <Muted>Код отправлен на {phone}</Muted>
          <Field label="Код из SMS" keyboardType="number-pad" value={code} onChangeText={setCode} maxLength={6} autoFocus />
          {isNew ? (
            <>
              <Field label="Как к вам обращаться" value={name} onChangeText={setName} placeholder="Имя и фамилия" />
              <Pressable style={styles.consent} onPress={() => setConsent(!consent)}>
                <View style={[styles.checkbox, consent && styles.checked]}>{consent ? <Text style={styles.tick}>✓</Text> : null}</View>
                <Text style={styles.consentText}>
                  Согласен на обработку персональных данных в соответствии с политикой конфиденциальности компании.
                </Text>
              </Pressable>
            </>
          ) : null}
          <Button title="Войти" loading={loading} onPress={verify} disabled={code.length < 4 || (isNew && !consent)} />
          <Button title="Изменить номер" kind="secondary" onPress={() => setStep('phone')} />
        </>
      )}
      {error ? <ErrorBox message={error} /> : null}
    </Screen>
  );
}

const styles = StyleSheet.create({
  consent: { flexDirection: 'row', gap: Spacing.two, alignItems: 'flex-start' },
  checkbox: { width: 24, height: 24, borderRadius: 6, borderWidth: 2, borderColor: Colors.primary, alignItems: 'center', justifyContent: 'center' },
  checked: { backgroundColor: Colors.primary },
  tick: { color: Colors.primaryText, fontWeight: '700' },
  consentText: { flex: 1, fontSize: 14, color: Colors.text, lineHeight: 20 },
});
