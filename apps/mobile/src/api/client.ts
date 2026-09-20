import { tokenStorage } from "../auth/tokenStorage";
import type { ApiResult, AuthTokens } from "./types";

const BASE_URL = process.env.EXPO_PUBLIC_API_URL ?? "http://localhost:8000/api/v1";

export class ApiRequestError extends Error {
  constructor(
    public readonly code: string,
    message: string,
    public readonly status: number,
    public readonly details?: unknown,
  ) {
    super(message);
  }
}

/** Thrown when the refresh token itself is invalid/expired — caller should log out. */
export class SessionExpiredError extends Error {}

interface RequestOptions {
  method?: "GET" | "POST" | "PUT" | "DELETE";
  body?: unknown;
  auth?: boolean;
}

async function rawRequest<T>(path: string, options: RequestOptions, accessToken: string | null): Promise<ApiResult<T>> {
  const headers: Record<string, string> = { "Content-Type": "application/json" };

  if (options.auth !== false && accessToken) {
    headers.Authorization = `Bearer ${accessToken}`;
  }

  const response = await fetch(`${BASE_URL}${path}`, {
    method: options.method ?? "GET",
    headers,
    body: options.body ? JSON.stringify(options.body) : undefined,
  });

  return (await response.json()) as ApiResult<T>;
}

async function refreshTokens(): Promise<string> {
  const refreshToken = await tokenStorage.getRefreshToken();

  if (!refreshToken) {
    throw new SessionExpiredError("No refresh token stored.");
  }

  const result = await rawRequest<AuthTokens>(
    "/auth/refresh",
    { method: "POST", body: { refresh_token: refreshToken }, auth: false },
    null,
  );

  if (!result.success) {
    await tokenStorage.clear();
    throw new SessionExpiredError(result.error.message);
  }

  await tokenStorage.setTokens(result.data.access_token, result.data.refresh_token);

  return result.data.access_token;
}

export async function apiRequest<T>(path: string, options: RequestOptions = {}): Promise<T> {
  let accessToken = options.auth === false ? null : await tokenStorage.getAccessToken();

  let result = await rawRequest<T>(path, options, accessToken);

  if (!result.success && result.error.code === "UNAUTHENTICATED" && options.auth !== false) {
    accessToken = await refreshTokens();
    result = await rawRequest<T>(path, options, accessToken);
  }

  if (!result.success) {
    throw new ApiRequestError(result.error.code, result.error.message, 0, result.error.details);
  }

  return result.data;
}
