import axios from 'axios'

export function formatApiError(
  err: unknown,
  fallback = 'Something went wrong.',
): string {
  if (!axios.isAxiosError(err)) {
    return fallback
  }

  if (!err.response) {
    const base =
      import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api/v1'
    return `Cannot reach the API at ${base}. Start the backend with php artisan serve in the backend folder (port 8000), then reload.`
  }

  const { status, data } = err.response
  const payload = data as {
    message?: string
    errors?: Record<string, string[]>
  }

  if (status === 422) {
    const fromErrors = payload.errors
      ? Object.values(payload.errors).flat().join(' ')
      : ''
    return fromErrors || payload.message || 'Invalid email or password.'
  }

  if (payload.message) {
    return payload.message
  }

  return `Request failed (${status}).`
}
