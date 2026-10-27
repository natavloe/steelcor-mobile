/**
 * Цвета и отступы приложения «Стилкор». Светлая тема — основная (B2B, работа на улице и в офисе).
 */

export const Colors = {
  primary: '#1E3A8A',
  primaryText: '#FFFFFF',
  accent: '#F59E0B',
  text: '#111827',
  textSecondary: '#6B7280',
  background: '#F5F6F8',
  card: '#FFFFFF',
  border: '#E5E7EB',
  danger: '#DC2626',
  success: '#16A34A',
  warning: '#D97706',
  info: '#2563EB',
} as const;

export const Spacing = {
  half: 2,
  one: 4,
  two: 8,
  three: 16,
  four: 24,
  five: 32,
} as const;

export const Radius = { sm: 8, md: 12, lg: 16 } as const;

export const MaxContentWidth = 720;
