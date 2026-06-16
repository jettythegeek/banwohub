type ResolveApiUrlOptions = {
  configuredUrl?: string
  origin?: string
  port?: string
}

const LOCAL_API_URL = 'http://127.0.0.1:8000/api/v1'

export function resolveApiUrl(options: ResolveApiUrlOptions = {}): string {
  if (options.configuredUrl) {
    return options.configuredUrl
  }

  if (options.origin) {
    return options.port === '3000' ? LOCAL_API_URL : `${options.origin}/api/v1`
  }

  return LOCAL_API_URL
}

