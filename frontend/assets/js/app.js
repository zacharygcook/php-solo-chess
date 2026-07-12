import { createApiClient } from './api.js';
import { boardFromFen, getPieceAt, renderBoard } from './board.js';
import { createUiState } from './state.js';

const api = createApiClient(document.body.dataset.apiBase);
const uiState = createUiState();
const elements = {};

document.addEventListener('DOMContentLoaded', init);

function init() {
    cacheDom();
    bindEvents();
    handleResize();
    window.addEventListener('resize', handleResize);
    loadState();
}

function cacheDom() {
    elements.board = document.querySelector('#chessBoard');
    elements.history = document.querySelector('#moveHistory');
    elements.status = document.querySelector('#statusMessage');
    elements.activeColor = document.querySelector('#activeColor');
    elements.activeMoveLabel = document.querySelector('#activeMoveLabel');
    elements.accountBadge = document.querySelector('#accountBadge');
    elements.accountSummary = document.querySelector('#accountSummary');
    elements.refresh = document.querySelector('#refreshButton');
    elements.reset = document.querySelector('#resetButton');
    elements.quickGuest = document.querySelector('#quickGuestButton');
    elements.newGameForm = document.querySelector('#newGameForm');
    elements.newGameMessage = document.querySelector('#newGameMessage');
    elements.loginForm = document.querySelector('#loginForm');
    elements.registerForm = document.querySelector('#registerForm');
    elements.logout = document.querySelector('#logoutButton');
    elements.authMessage = document.querySelector('#authMessage');
    elements.refreshHistory = document.querySelector('#refreshHistoryButton');
    elements.savedGames = document.querySelector('#savedGames');
    elements.savedGamesMessage = document.querySelector('#savedGamesMessage');
    elements.returnLive = document.querySelector('#returnLiveButton');
    elements.replayControls = document.querySelector('#replayControls');
    elements.replayPrevious = document.querySelector('#replayPreviousButton');
    elements.replayNext = document.querySelector('#replayNextButton');
    elements.reviewBanner = document.querySelector('#reviewBanner');
    elements.reviewTitle = document.querySelector('#reviewTitle');
    elements.reviewStepLabel = document.querySelector('#reviewStepLabel');
    elements.moveHistoryTitle = document.querySelector('#moveHistoryTitle');
    elements.fenForm = document.querySelector('#fenForm');
    elements.fenInput = document.querySelector('#fenInput');
    elements.header = document.querySelector('.app-header');
}

function bindEvents() {
    elements.refresh.addEventListener('click', loadState);
    elements.reset.addEventListener('click', resetGame);
    elements.quickGuest.addEventListener('click', startQuickGuestGame);
    elements.newGameForm.addEventListener('submit', (event) => {
        event.preventDefault();
        startConfiguredGame();
    });
    elements.loginForm.addEventListener('submit', (event) => {
        event.preventDefault();
        submitLogin();
    });
    elements.registerForm.addEventListener('submit', (event) => {
        event.preventDefault();
        submitRegistration();
    });
    elements.logout.addEventListener('click', logout);
    elements.refreshHistory.addEventListener('click', refreshHistory);
    elements.returnLive.addEventListener('click', returnToLiveBoard);
    elements.replayPrevious.addEventListener('click', () => stepReplay(-1));
    elements.replayNext.addEventListener('click', () => stepReplay(1));
    elements.savedGames.addEventListener('click', handleSavedGameClick);
    elements.fenForm.addEventListener('submit', (event) => {
        event.preventDefault();
        submitFen();
    });
}

async function loadState() {
    setStatus('Loading session...');

    try {
        applyState(await api.loadSession());
        await loadUser();
    } catch (_error) {
        setStatus('Failed to reach backend session endpoint.');
    }
}

async function loadUser() {
    try {
        applyUser(await api.currentUser());
    } catch (_error) {
        uiState.setUser(null);
        renderUser();
        setAuthMessage('Account status is unavailable.');
    }
}

async function resetGame() {
    setStatus('Resetting session...');

    try {
        uiState.clearSelection();
        applyState(await api.resetGame());
    } catch (_error) {
        setStatus('Reset failed. Check backend logs.');
    }
}

async function submitFen() {
    const fen = elements.fenInput.value.trim();
    if (!fen) {
        setStatus('Provide a FEN string first.');
        return;
    }

    setStatus('Sending FEN placeholder request...');

    try {
        applyState(await api.submitFen(fen));
    } catch (_error) {
        setStatus('FEN request failed.');
    }
}

function handleSquareAction(coord) {
    if (uiState.isReviewing) {
        setStatus('Review mode is read-only. Return to the live board to move.');
        return;
    }

    const gameState = uiState.gameState;
    if (!gameState) {
        return;
    }

    const piece = getPieceAt(gameState.board || [], coord);
    const selection = uiState.selection;

    if (!selection.from) {
        if (!piece) {
            flashSelectionError('Select a square with a piece.');
            return;
        }
        uiState.setSelection({ from: coord, to: null });
    } else if (selection.from === coord) {
        uiState.clearSelection();
    } else {
        uiState.setSelection({ from: selection.from, to: coord });
        submitMove();
    }

    renderCurrentBoard();
}

async function submitMove() {
    const selection = uiState.selection;
    if (!selection.from || !selection.to) {
        return;
    }

    const payload = {
        from: selection.from,
        to: selection.to,
    };

    setStatus(`Submitting move ${payload.from} to ${payload.to}...`);

    try {
        const response = await api.submitMove(payload);
        uiState.clearSelection();
        applyState(response);
    } catch (_error) {
        setStatus('Move request failed.');
    } finally {
        uiState.clearSelection();
    }
}

async function startQuickGuestGame() {
    await createGame({
        participants: {
            white: { label: 'White', type: 'local_human' },
            black: { label: 'Black', type: 'local_human' },
        },
        timeControl: { kind: 'untimed' },
    });
}

async function startConfiguredGame() {
    const payload = configuredGamePayload();
    if (!payload) {
        return;
    }

    await createGame(payload);
}

async function createGame(payload) {
    setNewGameMessage('Creating game...');

    try {
        const response = await api.createGame(payload);
        if (response.state) {
            applyState(response);
        }
        setNewGameMessage(response.message || 'Game ready.');
        if (response.success) {
            await refreshHistory();
        }
    } catch (_error) {
        setNewGameMessage('New game request failed.');
    }
}

async function submitLogin() {
    setAuthMessage('Logging in...');

    try {
        const response = await api.loginUser(formFields(elements.loginForm, ['username', 'password']));
        applyUser(response);
        setAuthMessage(response.message || 'Login complete.');
        if (response.success) {
            elements.loginForm.reset();
            await refreshHistory();
        }
    } catch (_error) {
        setAuthMessage('Login request failed.');
    }
}

async function submitRegistration() {
    setAuthMessage('Registering...');

    try {
        const response = await api.registerUser(
            formFields(elements.registerForm, ['username', 'displayName', 'password']),
        );
        applyUser(response);
        setAuthMessage(response.message || 'Registration complete.');
        if (response.success) {
            elements.registerForm.reset();
            await refreshHistory();
        }
    } catch (_error) {
        setAuthMessage('Registration request failed.');
    }
}

async function logout() {
    setAuthMessage('Logging out...');

    try {
        const response = await api.logoutUser();
        applyUser(response);
        setAuthMessage(response.message || 'Logged out.');
        clearSavedGames('Log in to view saved games.');
        returnToLiveBoard();
    } catch (_error) {
        setAuthMessage('Logout request failed.');
    }
}

async function refreshHistory() {
    if (!uiState.user) {
        clearSavedGames('Log in to view saved games.');
        return;
    }

    elements.savedGamesMessage.textContent = 'Loading saved games...';

    try {
        const response = await api.loadHistory();
        if (!response.success) {
            clearSavedGames(response.message || 'Saved games are unavailable.');
            return;
        }
        renderSavedGames(response.state?.games || []);
    } catch (_error) {
        clearSavedGames('Saved game request failed.');
    }
}

async function handleSavedGameClick(event) {
    const button = event.target.closest('button[data-game-id]');
    if (!button) {
        return;
    }

    const gameId = button.dataset.gameId;
    const fromStart = button.dataset.action === 'replay';
    await openSavedGame(gameId, fromStart);
}

async function openSavedGame(gameId, fromStart) {
    elements.savedGamesMessage.textContent = 'Opening saved game...';

    try {
        const response = fromStart ? await api.loadReplay(gameId) : await api.openGame(gameId);
        if (!response.success) {
            elements.savedGamesMessage.textContent = response.message || 'Saved game could not be opened.';
            return;
        }

        const replay = response.state?.replay;
        const game = response.state?.game;
        uiState.startReplay(replay, game, fromStart ? 0 : undefined);
        renderReviewMode(response.message || 'Saved game loaded.');
    } catch (_error) {
        elements.savedGamesMessage.textContent = 'Saved game request failed.';
    }
}

function applyState(response) {
    if (!response || !response.state) {
        setStatus('Malformed response from backend.');
        return;
    }

    uiState.stopReplay();
    uiState.setGameState(response.state);
    renderCurrentBoard();
    renderHistory(response.state.moveHistory || []);
    setStatus(response.message || 'Ready.');

    const active = (response.state.activeColor || '').toUpperCase();
    elements.activeColor.textContent = active ? `${active} to move` : '-';
    elements.activeMoveLabel.textContent = active || '-';
}

function applyUser(response) {
    const user = response?.state?.user || null;
    uiState.setUser(user);
    renderUser();

    if (user) {
        refreshHistory();
    } else {
        clearSavedGames('Log in to view saved games.');
    }
}

function renderCurrentBoard() {
    if (uiState.isReviewing) {
        const position = currentReplayPosition();
        const replayBoard = boardFromFen(position?.fen);
        renderBoard(elements.board, replayBoard, { from: null, to: null }, handleSquareAction);
    } else {
        const gameState = uiState.gameState;
        renderBoard(elements.board, gameState?.board || [], uiState.selection, handleSquareAction);
    }

    renderReviewBanner();
    handleResize();
}

function renderHistory(moves, activePly = null) {
    elements.history.replaceChildren();
    elements.moveHistoryTitle.textContent = uiState.isReviewing ? 'Replay Moves' : 'Move History';

    moves.forEach((move, index) => {
        const item = document.createElement('li');
        item.textContent = `${index + 1}. ${moveLabel(move)}`;
        if (activePly !== null && index + 1 === activePly) {
            item.classList.add('active');
        }
        elements.history.append(item);
    });
}

function renderUser() {
    const user = uiState.user;
    if (user) {
        elements.accountBadge.textContent = user.displayName || user.username;
        elements.accountSummary.textContent = `${user.displayName || user.username} (${user.username})`;
        elements.logout.disabled = false;
        return;
    }

    elements.accountBadge.textContent = 'Guest session';
    elements.accountSummary.textContent = 'Guest games stay in this browser session. Log in to save history.';
    elements.logout.disabled = true;
}

function renderSavedGames(games) {
    elements.savedGames.replaceChildren();

    if (games.length === 0) {
        elements.savedGamesMessage.textContent = 'No saved games yet.';
        return;
    }

    elements.savedGamesMessage.textContent = `${games.length} saved game${games.length === 1 ? '' : 's'}.`;
    games.forEach((game) => {
        const item = document.createElement('li');
        const summary = document.createElement('div');
        summary.className = 'saved-game-summary';
        const title = document.createElement('strong');
        title.textContent = `${game.whiteLabel || 'White'} vs ${game.blackLabel || 'Black'}`;
        const metadata = document.createElement('span');
        metadata.textContent = `${game.timeControl?.label || 'Untimed'} - ${game.status || 'active'} - ${formatDate(game.date)}`;
        summary.append(title, metadata);

        const actions = document.createElement('div');
        actions.className = 'saved-game-actions';
        actions.append(savedGameButton(game.id, 'open', 'Open'));
        actions.append(savedGameButton(game.id, 'replay', 'Replay'));

        item.append(summary, actions);
        elements.savedGames.append(item);
    });
}

function clearSavedGames(message) {
    elements.savedGames.replaceChildren();
    elements.savedGamesMessage.textContent = message;
}

function renderReviewMode(message) {
    const replay = uiState.replay;
    const activePly = currentReplayPosition()?.plyNumber || 0;
    renderCurrentBoard();
    renderHistory(replay?.moves || [], activePly);
    setStatus(message);
    elements.savedGamesMessage.textContent = 'Review mode is read-only. Return to the live board to move.';
}

function renderReviewBanner() {
    if (!uiState.isReviewing) {
        elements.reviewBanner.hidden = true;
        elements.replayControls.hidden = true;
        return;
    }

    const replay = uiState.replay;
    const positionCount = replay?.positions.length || 0;
    elements.reviewBanner.hidden = false;
    elements.replayControls.hidden = false;
    elements.reviewTitle.textContent = `Reviewing saved game #${replay?.game?.id || '-'}`;
    elements.reviewStepLabel.textContent = `Step ${(replay?.index || 0) + 1} of ${positionCount || 1}`;
    elements.replayPrevious.disabled = !replay || replay.index <= 0;
    elements.replayNext.disabled = !replay || replay.index >= replay.positions.length - 1;
}

function returnToLiveBoard() {
    uiState.stopReplay();
    renderCurrentBoard();
    renderHistory(uiState.gameState?.moveHistory || []);
    setStatus('Live board ready.');
}

function stepReplay(delta) {
    uiState.stepReplay(delta);
    renderReviewMode('Replay position loaded.');
}

function setStatus(message) {
    elements.status.textContent = message;
}

function setAuthMessage(message) {
    elements.authMessage.textContent = message;
}

function setNewGameMessage(message) {
    elements.newGameMessage.textContent = message;
}

function flashSelectionError(message) {
    setStatus(message);
    window.setTimeout(() => {
        setStatus('');
    }, 2000);
}

function configuredGamePayload() {
    const timeKind = new FormData(elements.newGameForm).get('timeKind') || 'untimed';
    const payload = {
        participants: {
            white: {
                label: elements.newGameForm.elements.whiteLabel.value.trim() || 'White',
                type: elements.newGameForm.elements.whiteType.value,
            },
            black: {
                label: elements.newGameForm.elements.blackLabel.value.trim() || 'Black',
                type: elements.newGameForm.elements.blackType.value,
            },
        },
        timeControl: { kind: 'untimed' },
    };

    if (timeKind === 'preset') {
        payload.timeControl = {
            kind: 'preset',
            preset: elements.newGameForm.elements.timePreset.value,
        };
    }
    if (timeKind === 'custom') {
        const baseMinutes = Number(elements.newGameForm.elements.customBaseMinutes.value);
        const incrementSeconds = Number(elements.newGameForm.elements.customIncrementSeconds.value);
        if (!Number.isInteger(baseMinutes) || baseMinutes < 1 || baseMinutes > 180) {
            setNewGameMessage('Custom minutes must be between 1 and 180.');
            return null;
        }
        if (!Number.isInteger(incrementSeconds) || incrementSeconds < 0 || incrementSeconds > 60) {
            setNewGameMessage('Custom increment must be between 0 and 60 seconds.');
            return null;
        }
        payload.timeControl = { kind: 'custom', baseMinutes, incrementSeconds };
    }

    return payload;
}

function formFields(form, fieldNames) {
    return fieldNames.reduce((fields, name) => {
        fields[name] = form.elements[name].value.trim();

        return fields;
    }, {});
}

function currentReplayPosition() {
    const replay = uiState.replay;

    return replay?.positions[replay.index] || null;
}

function moveLabel(move) {
    if (move.san) {
        return move.san;
    }
    if (move.coordinate) {
        return move.coordinate;
    }

    return `${move.from || '??'} to ${move.to || '??'}`;
}

function savedGameButton(gameId, action, label) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = action === 'replay' ? 'secondary' : '';
    button.dataset.gameId = gameId;
    button.dataset.action = action;
    button.textContent = label;

    return button;
}

function formatDate(value) {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return 'undated';
    }

    return date.toLocaleString([], {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function handleResize() {
    if (!elements.board) {
        return;
    }

    const headerHeight = elements.header?.getBoundingClientRect().height || 0;
    const availableHeight = Math.max(260, window.innerHeight - headerHeight - 120);
    const boardPanel = elements.board.closest('.board-panel');
    const panelWidth = boardPanel?.getBoundingClientRect().width || elements.board.getBoundingClientRect().width;
    const boardSize = Math.max(240, Math.min(panelWidth, availableHeight));
    elements.board.style.width = `${boardSize}px`;
    elements.board.style.height = `${boardSize}px`;
}
