/** Базовые элементы интерфейса приложения. */
import type { ReactNode } from 'react';
import {
  ActivityIndicator,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
  type TextInputProps,
  type ViewStyle,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { Colors, MaxContentWidth, Radius, Spacing } from '@/constants/theme';

export function Screen({
  children,
  refreshing,
  onRefresh,
  footer,
}: {
  children: ReactNode;
  refreshing?: boolean;
  onRefresh?: () => void;
  footer?: ReactNode;
}) {
  return (
    <SafeAreaView style={styles.safe} edges={['left', 'right']}>
      <ScrollView
        contentContainerStyle={styles.scroll}
        keyboardShouldPersistTaps="handled"
        refreshControl={onRefresh ? <RefreshControl refreshing={!!refreshing} onRefresh={onRefresh} /> : undefined}>
        <View style={styles.content}>{children}</View>
      </ScrollView>
      {footer ? <View style={styles.footer}>{footer}</View> : null}
    </SafeAreaView>
  );
}

export function Card({ children, style }: { children: ReactNode; style?: ViewStyle }) {
  return <View style={[styles.card, style]}>{children}</View>;
}

export function Title({ children }: { children: ReactNode }) {
  return <Text style={styles.title}>{children}</Text>;
}

export function Subtitle({ children }: { children: ReactNode }) {
  return <Text style={styles.subtitle}>{children}</Text>;
}

export function Muted({ children }: { children: ReactNode }) {
  return <Text style={styles.muted}>{children}</Text>;
}

export function Row({ label, value }: { label: string; value: ReactNode }) {
  return (
    <View style={styles.row}>
      <Text style={styles.rowLabel}>{label}</Text>
      {typeof value === 'string' || typeof value === 'number' ? <Text style={styles.rowValue}>{value}</Text> : value}
    </View>
  );
}

type ButtonKind = 'primary' | 'secondary' | 'danger';

export function Button({
  title,
  onPress,
  kind = 'primary',
  loading,
  disabled,
}: {
  title: string;
  onPress: () => void;
  kind?: ButtonKind;
  loading?: boolean;
  disabled?: boolean;
}) {
  const inactive = disabled || loading;
  return (
    <Pressable
      accessibilityRole="button"
      onPress={onPress}
      disabled={inactive}
      style={({ pressed }) => [styles.button, styles[`button_${kind}`], (pressed || inactive) && styles.buttonPressed]}>
      {loading ? (
        <ActivityIndicator color={kind === 'secondary' ? Colors.primary : Colors.primaryText} />
      ) : (
        <Text style={[styles.buttonText, kind === 'secondary' && styles.buttonTextSecondary]}>{title}</Text>
      )}
    </Pressable>
  );
}

export function Field({ label, error, hint, ...props }: TextInputProps & { label: string; error?: string; hint?: string }) {
  return (
    <View style={styles.field}>
      <Text style={styles.fieldLabel}>{label}</Text>
      <TextInput placeholderTextColor={Colors.textSecondary} style={[styles.input, error ? styles.inputError : null]} {...props} />
      {error ? <Text style={styles.error}>{error}</Text> : hint ? <Text style={styles.hint}>{hint}</Text> : null}
    </View>
  );
}

const BADGE_COLORS: Record<string, string> = {
  success: Colors.success,
  warning: Colors.warning,
  danger: Colors.danger,
  info: Colors.info,
  muted: Colors.textSecondary,
};

export function Badge({ text, tone = 'muted' }: { text: string; tone?: keyof typeof BADGE_COLORS }) {
  return (
    <View style={[styles.badge, { borderColor: BADGE_COLORS[tone] }]}>
      <Text style={[styles.badgeText, { color: BADGE_COLORS[tone] }]}>{text}</Text>
    </View>
  );
}

export function Chip({ text, active, onPress }: { text: string; active?: boolean; onPress: () => void }) {
  return (
    <Pressable onPress={onPress} style={[styles.chip, active && styles.chipActive]}>
      <Text style={[styles.chipText, active && styles.chipTextActive]}>{text}</Text>
    </Pressable>
  );
}

export function Loading() {
  return (
    <View style={styles.center}>
      <ActivityIndicator color={Colors.primary} size="large" />
    </View>
  );
}

export function ErrorBox({ message, onRetry }: { message: string; onRetry?: () => void }) {
  return (
    <Card style={styles.errorBox}>
      <Text style={styles.error}>{message}</Text>
      {onRetry ? <Button title="Повторить" kind="secondary" onPress={onRetry} /> : null}
    </Card>
  );
}

export function Empty({ title, children }: { title: string; children?: ReactNode }) {
  return (
    <View style={styles.center}>
      <Text style={styles.subtitle}>{title}</Text>
      {children}
    </View>
  );
}

export function Notice({ children, tone = 'info' }: { children: ReactNode; tone?: 'info' | 'warning' }) {
  return (
    <View style={[styles.notice, tone === 'warning' && styles.noticeWarning]}>
      <Text style={styles.noticeText}>{children}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: Colors.background },
  scroll: { padding: Spacing.three, paddingBottom: Spacing.five },
  content: { width: '100%', maxWidth: MaxContentWidth, alignSelf: 'center', gap: Spacing.three },
  footer: {
    padding: Spacing.three,
    backgroundColor: Colors.card,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: Colors.border,
  },
  card: {
    backgroundColor: Colors.card,
    borderRadius: Radius.md,
    padding: Spacing.three,
    gap: Spacing.two,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: Colors.border,
  },
  title: { fontSize: 22, fontWeight: '700', color: Colors.text },
  subtitle: { fontSize: 17, fontWeight: '600', color: Colors.text },
  muted: { fontSize: 14, color: Colors.textSecondary },
  row: { flexDirection: 'row', justifyContent: 'space-between', gap: Spacing.three },
  rowLabel: { fontSize: 14, color: Colors.textSecondary, flexShrink: 0 },
  rowValue: { fontSize: 14, color: Colors.text, textAlign: 'right', flexShrink: 1 },
  button: { minHeight: 48, borderRadius: Radius.md, alignItems: 'center', justifyContent: 'center', paddingHorizontal: Spacing.three },
  button_primary: { backgroundColor: Colors.primary },
  button_secondary: { backgroundColor: Colors.card, borderWidth: 1, borderColor: Colors.primary },
  button_danger: { backgroundColor: Colors.danger },
  buttonPressed: { opacity: 0.6 },
  buttonText: { color: Colors.primaryText, fontSize: 16, fontWeight: '600' },
  buttonTextSecondary: { color: Colors.primary },
  field: { gap: Spacing.one },
  fieldLabel: { fontSize: 14, color: Colors.textSecondary },
  input: {
    minHeight: 48,
    borderWidth: 1,
    borderColor: Colors.border,
    borderRadius: Radius.sm,
    paddingHorizontal: Spacing.three,
    fontSize: 16,
    color: Colors.text,
    backgroundColor: Colors.card,
  },
  inputError: { borderColor: Colors.danger },
  error: { color: Colors.danger, fontSize: 14 },
  hint: { color: Colors.textSecondary, fontSize: 13 },
  badge: { alignSelf: 'flex-start', borderWidth: 1, borderRadius: 999, paddingHorizontal: Spacing.two, paddingVertical: 2 },
  badgeText: { fontSize: 12, fontWeight: '600' },
  chip: {
    paddingHorizontal: Spacing.three,
    paddingVertical: Spacing.two,
    borderRadius: 999,
    borderWidth: 1,
    borderColor: Colors.border,
    backgroundColor: Colors.card,
  },
  chipActive: { backgroundColor: Colors.primary, borderColor: Colors.primary },
  chipText: { color: Colors.text, fontSize: 14 },
  chipTextActive: { color: Colors.primaryText },
  center: { padding: Spacing.five, alignItems: 'center', justifyContent: 'center', gap: Spacing.three },
  errorBox: { borderColor: Colors.danger },
  notice: { backgroundColor: '#EFF6FF', borderRadius: Radius.sm, padding: Spacing.three },
  noticeWarning: { backgroundColor: '#FFFBEB' },
  noticeText: { color: Colors.text, fontSize: 14, lineHeight: 20 },
});
