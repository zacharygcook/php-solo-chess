export function createUiState() {
    let gameState = null;
    let selection = { from: null, to: null };

    return {
        get gameState() {
            return gameState;
        },
        get selection() {
            return selection;
        },
        setGameState(nextState) {
            gameState = nextState;
        },
        setSelection(nextSelection) {
            selection = nextSelection;
        },
        clearSelection() {
            selection = { from: null, to: null };
        },
    };
}
