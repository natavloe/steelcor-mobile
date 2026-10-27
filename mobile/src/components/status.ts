/** Цвет значка статуса заказа для клиента. */
export function orderTone(status: string): 'success' | 'warning' | 'danger' | 'info' | 'muted' {
  if (['completed', 'delivered'].includes(status)) return 'success';
  if (status === 'cancelled') return 'danger';
  if (['awaiting_terms', 'awaiting_payment'].includes(status)) return 'warning';
  return 'info';
}
