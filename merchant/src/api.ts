const API_BASE = import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api/v1'

export async function prepareCsrf(): Promise<Record<string, string>> {
  const origin = API_BASE.replace(/\/api\/v1$/, '')
  await fetch(`${origin}/sanctum/csrf-cookie`, { credentials: 'include' })
  const token = document.cookie.split('; ').find((value) => value.startsWith('XSRF-TOKEN='))?.split('=')[1]
  return token ? { 'X-XSRF-TOKEN': decodeURIComponent(token) } : {}
}

export async function apiRequest<T>(path: string, init: RequestInit = {}): Promise<T> {
  const response = await fetch(`${API_BASE}${path}`, {
    ...init,
    credentials: 'include',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...init.headers },
  })
  const body = await response.json().catch(() => ({}))
  if (!response.ok) throw new Error(body.message ?? 'ไม่สามารถเชื่อมต่อระบบได้')
  return body as T
}
