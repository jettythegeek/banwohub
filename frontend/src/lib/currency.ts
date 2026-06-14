export const CURRENCY_CODE = 'USD'
export const CURRENCY_SYMBOL = '$'
export const CURRENCY_LOCALE = 'en-US'

export function formatCurrency(
  amount?: number | null,
  currency: string = CURRENCY_CODE,
): string {
  if (amount === null || amount === undefined) return '—'
  return new Intl.NumberFormat(CURRENCY_LOCALE, {
    style: 'currency',
    currency,
  }).format(amount)
}
