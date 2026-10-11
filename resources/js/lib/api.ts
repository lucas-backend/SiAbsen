export type ValidationErrors = Record<string, string[]>;

export class ApiError extends Error {
    public readonly status: number;
    public readonly errors: ValidationErrors;

    constructor(
        message: string,
        status: number,
        errors: ValidationErrors = {},
    ) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.errors = errors;
    }

    firstError(field?: string): string | undefined {
        if (field && this.errors[field]?.length) {
            return this.errors[field][0];
        }

        const first = Object.values(this.errors)[0];

        return first?.[0];
    }
}

function getXsrfToken(): string | null {
    const match = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='));

    return match
        ? decodeURIComponent(match.split('=').slice(1).join('='))
        : null;
}

export type ApiMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

export type ApiOptions = {
    method?: ApiMethod;
    body?: unknown;
};

/**
 * Minimal JSON client for the domain API. Sends the Laravel XSRF token so
 * state-changing requests pass CSRF verification while remaining same-origin.
 */
export async function apiRequest<T>(
    url: string,
    options: ApiOptions = {},
): Promise<T> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    const token = getXsrfToken();

    if (token) {
        headers['X-XSRF-TOKEN'] = token;
    }

    if (options.body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }

    const response = await fetch(url, {
        method: options.method ?? 'GET',
        headers,
        credentials: 'same-origin',
        body:
            options.body === undefined
                ? undefined
                : JSON.stringify(options.body),
    });

    const payload = (await response.json().catch(() => null)) as unknown;

    if (!response.ok) {
        const data = (payload ?? {}) as {
            message?: string;
            errors?: ValidationErrors;
        };

        throw new ApiError(
            data.message ?? `Permintaan gagal (${response.status}).`,
            response.status,
            data.errors ?? {},
        );
    }

    return payload as T;
}
