/** Форматирование денег, количеств, единиц и дат для интерфейса. */
import type { Unit } from '@/api/types';

export const UNIT_LABELS: Record<Unit, string> = { t: 'т', kg: 'кг', m: 'м', pc: 'шт', m2: 'м²' };

export function money(value: string | number | null | undefined): string {
  if (value === null || value === undefined || value === '') return '—';
  const n = typeof value === 'number' ? value : Number(value);
  return n.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₽';
}

export function qty(value: string | number, unit?: Unit): string {
  const n = typeof value === 'number' ? value : Number(value);
  const text = n.toLocaleString('ru-RU', { maximumFractionDigits: 4 });
  return unit ? `${text} ${UNIT_LABELS[unit]}` : text;
}

export function date(value: string | null | undefined): string {
  if (!value) return '—';
  const d = new Date(value.length === 10 ? value + 'T00:00:00' : value);
  return d.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

export function dateTime(value: string | null | undefined): string {
  if (!value) return '—';
  return new Date(value).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

/** Ввод числа с запятой: «1,5» → «1.5». */
export function parseNumber(text: string): string {
  return text.replace(',', '.').replace(/[^0-9.]/g, '');
}
