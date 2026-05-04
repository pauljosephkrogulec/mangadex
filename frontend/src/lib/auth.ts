function getApiBaseUrl(): string {
  return process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';
}

export interface LoginCredentials {
  email: string;
  password: string;
}

export interface LoginResponse {
  token: string;
}

export function getToken(): string | null {
  if (typeof window === 'undefined') return null; // eslint-disable-line -- SSR guard, not testable in jsdom
  return localStorage.getItem('jwt_token');
}

export function setToken(token: string): void {
  localStorage.setItem('jwt_token', token);
}

export function removeToken(): void {
  localStorage.removeItem('jwt_token');
}

export async function login(credentials: LoginCredentials): Promise<string> {
  const response = await fetch(`${getApiBaseUrl()}/login_check`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(credentials),
  });

  if (!response.ok) {
    throw new Error('Login failed');
  }

  const data: LoginResponse = await response.json();
  setToken(data.token);
  return data.token;
}

export async function logout(): Promise<void> {
  removeToken();
}

export function getAuthHeaders(): Record<string, string> {
  const token = getToken();
  if (!token) return {};
  return {
    'Authorization': `Bearer ${token}`,
  };
}

export async function apiRequest(url: string, options: RequestInit = {}): Promise<Response> {
  const headers = {
    'Content-Type': 'application/json',
    ...getAuthHeaders(),
    ...options.headers,
  };

  return fetch(`${getApiBaseUrl()}${url}`, {
    ...options,
    headers,
  });
}
