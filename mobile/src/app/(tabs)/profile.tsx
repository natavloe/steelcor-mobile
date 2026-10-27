import { router } from 'expo-router';

import { API_URL } from '@/api/client';
import { Badge, Button, Card, Chip, Muted, Row, Screen, Subtitle, Title } from '@/components/ui';
import { useSession } from '@/state/session';

/** Профиль: город, организации, выход. */
export default function ProfileScreen() {
  const { user, token, region, organizationId, setOrganization, signOut } = useSession();

  return (
    <Screen>
      <Title>Профиль</Title>
      <Card>
        <Row label="Город" value={region ?? '—'} />
        <Button title="Сменить город" kind="secondary" onPress={() => router.push('/region')} />
      </Card>

      {token && user ? (
        <>
          <Card>
            <Subtitle>{user.name ?? 'Без имени'}</Subtitle>
            <Muted>{user.phone}</Muted>
          </Card>
          <Card>
            <Subtitle>Организации</Subtitle>
            {user.organizations.map((o) => (
              <Card key={o.id}>
                <Chip text={`${o.name} (ИНН ${o.inn})`} active={o.id === organizationId} onPress={() => setOrganization(o.id)} />
                <Badge
                  text={o.verification_status === 'verified' ? 'Реквизиты проверены' : 'Ждёт проверки менеджером'}
                  tone={o.verification_status === 'verified' ? 'success' : 'warning'}
                />
              </Card>
            ))}
            <Button title="+ Добавить организацию" kind="secondary" onPress={() => router.push('/organization-new')} />
          </Card>
          <Button title="Выйти" kind="danger" onPress={signOut} />
        </>
      ) : (
        <Button title="Войти по номеру телефона" onPress={() => router.push('/login')} />
      )}
      <Muted>Сервер: {API_URL}</Muted>
    </Screen>
  );
}
