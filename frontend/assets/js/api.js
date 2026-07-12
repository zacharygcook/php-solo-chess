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
        const payload = await readJson(response);

        if (!response.ok && !payload) {
            throw new Error(`Request failed with HTTP ${response.status}`);
        }

        return {
            ...(payload || {
                success: false,
                message: `Request failed with HTTP ${response.status}`,
                state: {},
            }),
            httpStatus: response.status,
        };
    }

    return {
        currentUser() {
            return request('auth/user.php');
        },
        registerUser(credentials) {
            return request('auth/register.php', {
                method: 'POST',
                body: JSON.stringify(credentials),
            });
        },
        loginUser(credentials) {
            return request('auth/login.php', {
                method: 'POST',
                body: JSON.stringify(credentials),
            });
        },
        logoutUser() {
            return request('auth/logout.php', { method: 'POST' });
        },
        loadSession() {
            return request('session.php');
        },
        createGame(payload) {
            return request('games/new.php', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
        },
        resetGame() {
            return request('reset.php', { method: 'POST' });
        },
        loadHistory() {
            return request('games/history.php');
        },
        openGame(gameId) {
            return request(`games/open.php?id=${encodeURIComponent(gameId)}`);
        },
        loadReplay(gameId) {
            return request(`games/replay.php?id=${encodeURIComponent(gameId)}`);
        },
        abandonGame(payload) {
            return request('games/abandon.php', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
        },
        resignGame(payload) {
            return request('games/resign.php', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
        },
        offerDraw(payload) {
            return request('games/draw-offer.php', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
        },
        acceptDraw(payload) {
            return request('games/draw-accept.php', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
        },
        claimDraw(payload) {
            return request('games/draw-claim.php', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
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

async function readJson(response) {
    try {
        return await response.json();
    } catch (_error) {
        return null;
    }
}
