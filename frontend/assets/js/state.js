export function createUiState() {
    let gameState = null;
    let user = null;
    let selection = { from: null, to: null };
    let replay = null;
    let orientation = 'white';

    return {
        get gameState() {
            return gameState;
        },
        get user() {
            return user;
        },
        get selection() {
            return selection;
        },
        get replay() {
            return replay;
        },
        get orientation() {
            return orientation;
        },
        get isReviewing() {
            return replay !== null;
        },
        setGameState(nextState) {
            gameState = nextState;
        },
        setUser(nextUser) {
            user = nextUser;
        },
        setSelection(nextSelection) {
            selection = nextSelection;
        },
        clearSelection() {
            selection = { from: null, to: null };
        },
        flipOrientation() {
            orientation = orientation === 'white' ? 'black' : 'white';
        },
        startReplay(nextReplay, game, index) {
            const positions = Array.isArray(nextReplay?.positions) ? nextReplay.positions : [];
            const lastIndex = positions.length > 0 ? positions.length - 1 : 0;
            replay = {
                game,
                positions,
                moves: Array.isArray(nextReplay?.moves) ? nextReplay.moves : [],
                index: typeof index === 'number' ? Math.max(0, Math.min(index, lastIndex)) : lastIndex,
            };
            selection = { from: null, to: null };
        },
        stepReplay(delta) {
            if (!replay || replay.positions.length === 0) {
                return;
            }

            replay = {
                ...replay,
                index: Math.max(0, Math.min(replay.index + delta, replay.positions.length - 1)),
            };
        },
        stopReplay() {
            replay = null;
            selection = { from: null, to: null };
        },
    };
}
