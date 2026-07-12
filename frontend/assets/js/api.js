export function createApiClient(apiBase) {
    const base = apiBase || '/api';

    async function request(path, options = {}) {
        const response = await fetch(`${base}/${path}`, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                ...(options.body ? { 'Content-Type': 'application/json' } : {}),
            },
            ...options,
        });

        if (!response.ok) {
            throw new Error(`Request failed with HTTP ${response.status}`);
        }

        return response.json();
    }

    return {
        loadSession() {
            return request('session.php');
        },
        resetGame() {
            return request('reset.php', { method: 'POST' });
        },
        submitFen(fen) {
            return request('setup.php', {
                method: 'POST',
                body: JSON.stringify({ fen }),
            });
        },
        submitMove(move) {
            return request('move.php', {
                method: 'POST',
                body: JSON.stringify(move),
            });
        },
    };
}
