const SOUND_STORAGE_KEY = 'soloChess.soundEnabled';

const SOUND_FILES = {
    move: 'move.wav',
    capture: 'capture.wav',
    check: 'check.wav',
    gameEnd: 'game-end.wav',
};

export function createAudioFeedback(options = {}) {
    const basePath = options.basePath || 'assets/audio';
    const storage = options.storage || globalThis.localStorage || null;
    const AudioCtor = options.AudioCtor || globalThis.Audio || null;
    const players = {};
    let enabled = readStoredPreference(storage);

    function isEnabled() {
        return enabled;
    }

    function setEnabled(nextEnabled) {
        enabled = Boolean(nextEnabled);
        writeStoredPreference(storage, enabled);

        if (enabled) {
            preloadPlayers();
        }

        return enabled;
    }

    function toggle() {
        return setEnabled(!enabled);
    }

    function play(kind) {
        if (!enabled || !SOUND_FILES[kind]) {
            return;
        }

        const player = playerFor(kind);
        if (!player) {
            return;
        }

        try {
            player.currentTime = 0;
            const result = player.play();
            if (result && typeof result.catch === 'function') {
                result.catch(() => {});
            }
        } catch (_error) {
            // Browsers can block or omit audio; sound is optional feedback.
        }
    }

    function preloadPlayers() {
        Object.keys(SOUND_FILES).forEach((kind) => {
            const player = playerFor(kind);
            if (player && typeof player.load === 'function') {
                try {
                    player.load();
                } catch (_error) {
                    // Missing or unsupported local audio must not affect play.
                }
            }
        });
    }

    function playerFor(kind) {
        if (!AudioCtor || !SOUND_FILES[kind]) {
            return null;
        }

        if (!players[kind]) {
            const player = new AudioCtor(`${basePath}/${SOUND_FILES[kind]}`);
            player.preload = 'auto';
            player.volume = 0.45;
            players[kind] = player;
        }

        return players[kind];
    }

    if (enabled) {
        preloadPlayers();
    }

    return {
        isEnabled,
        setEnabled,
        toggle,
        play,
        storageKey: SOUND_STORAGE_KEY,
    };
}

function readStoredPreference(storage) {
    try {
        return storage?.getItem(SOUND_STORAGE_KEY) === 'true';
    } catch (_error) {
        return false;
    }
}

function writeStoredPreference(storage, enabled) {
    try {
        storage?.setItem(SOUND_STORAGE_KEY, enabled ? 'true' : 'false');
    } catch (_error) {
        // Private browsing or locked storage still leaves the runtime usable.
    }
}
