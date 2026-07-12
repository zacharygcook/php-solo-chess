import { createApiClient } from './api.js';
import { createAudioFeedback } from './audio.js';
import { boardFromFen, findKingSquare, getPieceAt, pieceLabel, renderBoard } from './board.js';
import { createUiState } from './state.js';

const api = createApiClient(document.body.dataset.apiBase);
const audioFeedback = createAudioFeedback({ basePath: 'assets/audio' });
const uiState = createUiState();
const elements = {};
let pendingPromotionMove = null;
let clockIntervalId = null;
let feedbackTimeoutId = null;

const TERMINAL_COPY = {
    checkmate: ['Checkmate', 'The king is trapped and the result is final.'],
    stalemate: ['Stalemate', 'The side to move has no legal move and is not in check.'],
    timeout: ['Timeout', 'The server clock reached zero and recorded the result.'],
    resignation: ['Resignation', 'A player resigned and the result is final.'],
    agreedDraw: ['Agreed draw', 'Both sides accepted a draw.'],
    deadPosition: ['Dead position', 'Neither side can legally force checkmate.'],
    fiftyMoveRule: ['Fifty-move draw', 'The draw claim was accepted by the server.'],
    threefoldRepetition: ['Threefold repetition', 'The draw claim was accepted by the server.'],
    abandoned: ['Abandoned', 'The local game was stopped without a chess result.'],
};

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
    elements.boardPanel = document.querySelector('.board-panel');
    elements.history = document.querySelector('#moveHistory');
    elements.status = document.querySelector('#statusMessage');
    elements.activeColor = document.querySelector('#activeColor');
    elements.activeMoveLabel = document.querySelector('#activeMoveLabel');
    elements.boardOrientation = document.querySelector('#boardOrientation');
    elements.flipBoard = document.querySelector('#flipBoardButton');
    elements.whiteClock = document.querySelector('#whiteClock');
    elements.blackClock = document.querySelector('#blackClock');
    elements.whiteClockLabel = document.querySelector('#whiteClockLabel');
    elements.blackClockLabel = document.querySelector('#blackClockLabel');
    elements.whiteClockTime = document.querySelector('#whiteClockTime');
    elements.blackClockTime = document.querySelector('#blackClockTime');
    elements.drawOfferNotice = document.querySelector('#drawOfferNotice');
    elements.terminalSummary = document.querySelector('#terminalSummary');
    elements.terminalTitle = document.querySelector('#terminalTitle');
    elements.terminalDetail = document.querySelector('#terminalDetail');
    elements.capturedWhite = document.querySelector('#capturedWhite');
    elements.capturedBlack = document.querySelector('#capturedBlack');
    elements.promotionPanel = document.querySelector('#promotionPanel');
    elements.promotionChoices = document.querySelector('#promotionChoices');
    elements.promotionCancel = document.querySelector('#promotionCancelButton');
    elements.accountBadge = document.querySelector('#accountBadge');
    elements.accountSummary = document.querySelector('#accountSummary');
    elements.refresh = document.querySelector('#refreshButton');
    elements.reset = document.querySelector('#resetButton');
    elements.abandon = document.querySelector('#abandonButton');
    elements.resign = document.querySelector('#resignButton');
    elements.offerDraw = document.querySelector('#offerDrawButton');
    elements.acceptDraw = document.querySelector('#acceptDrawButton');
    elements.claimDraw = document.querySelector('#claimDrawButton');
    elements.soundToggle = document.querySelector('#soundToggleButton');
    elements.actionMessage = document.querySelector('#actionMessage');
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
    elements.abandon.addEventListener('click', () => submitGameAction('abandon'));
    elements.resign.addEventListener('click', () => submitGameAction('resign'));
    elements.offerDraw.addEventListener('click', () => submitGameAction('offerDraw'));
    elements.acceptDraw.addEventListener('click', () => submitGameAction('acceptDraw'));
    elements.claimDraw.addEventListener('click', () => submitGameAction('claimDraw'));
    elements.soundToggle.addEventListener('click', toggleSound);
    elements.flipBoard.addEventListener('click', flipBoard);
    elements.promotionChoices.addEventListener('click', handlePromotionChoice);
    elements.promotionCancel.addEventListener('click', cancelPromotion);
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
    setActionMessage('Resetting live game...');

    try {
        uiState.clearSelection();
        applyState(await api.resetGame());
        setActionMessage('Live game reset.');
    } catch (_error) {
        setStatus('Reset failed. Check backend logs.');
        setActionMessage('Reset failed.');
    }
}

async function submitGameAction(action) {
    if (uiState.isReviewing) {
        setActionMessage('Return to the live board before using game controls.');
        return;
    }

    const payload = actionPayload(action);
    if (!payload) {
        return;
    }

    setActionMessage(`${actionLabel(action)} request sent...`);
    const previousState = uiState.gameState;

    try {
        const response = await actionRequest(action, payload);
        applyState(response);
        playStateFeedback(previousState, response.state, response.success ? 'action' : null);
        setActionMessage(response.message || `${actionLabel(action)} complete.`);
    } catch (_error) {
        setActionMessage(`${actionLabel(action)} request failed.`);
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
        if (!canSelectSource(coord, piece, gameState)) {
            return;
        }
        uiState.setSelection({ from: coord, to: null });
    } else if (selection.from === coord) {
        uiState.clearSelection();
    } else {
        attemptMove(selection.from, coord);
    }

    renderCurrentBoard();
}

function handleSquareDrop(from, to) {
    if (uiState.isReviewing) {
        setStatus('Review mode is read-only. Return to the live board to move.');
        return;
    }

    if (!uiState.gameState) {
        return;
    }

    uiState.setSelection({ from, to: null });
    attemptMove(from, to);
    renderCurrentBoard();
}

function attemptMove(from, to) {
    const gameState = uiState.gameState;
    const piece = getPieceAt(gameState?.board || [], from);
    if (needsPromotionChoice(piece, to)) {
        pendingPromotionMove = { from, to };
        renderPromotionPanel(from, to);
        return;
    }

    submitMove({ from, to });
}

async function submitMove(payload) {
    if (!payload.from || !payload.to) {
        return;
    }

    setStatus(`Submitting move ${payload.from} to ${payload.to}...`);
    const previousState = uiState.gameState;

    try {
        const response = await api.submitMove(payload);
        uiState.clearSelection();
        applyState(response);
        playStateFeedback(previousState, response.state, response.success ? 'move' : null);
    } catch (_error) {
        setStatus('Move request failed.');
    } finally {
        pendingPromotionMove = null;
        hidePromotionPanel();
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
    renderBoardStatus();
    renderActionControls();
    renderDrawOffer();
    renderTerminalSummary();
    startClockRendering();
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
        renderBoard(elements.board, replayBoard, { from: null, to: null }, handleSquareAction, {
            orientation: uiState.orientation,
            lastMove: replayLastMove(),
            gameStatus: 'review',
            onDrop: handleSquareDrop,
        });
    } else {
        const gameState = uiState.gameState;
        renderBoard(elements.board, gameState?.board || [], uiState.selection, handleSquareAction, {
            orientation: uiState.orientation,
            legalMoves: gameState?.legalMoves || {},
            lastMove: lastMove(gameState),
            checkedKing: checkedKing(gameState),
            gameStatus: gameState?.gameStatus || 'active',
            onDrop: handleSquareDrop,
        });
    }

    renderReviewBanner();
    renderCaptures();
    renderBoardStatus();
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
    renderActionControls();
    renderDrawOffer();
    renderTerminalSummary();
    startClockRendering();
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
    renderActionControls();
    renderDrawOffer();
    renderTerminalSummary();
    startClockRendering();
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

function setActionMessage(message) {
    elements.actionMessage.textContent = message;
}

function flashSelectionError(message) {
    setStatus(message);
    window.setTimeout(() => {
        setStatus('');
    }, 2000);
}

function flipBoard() {
    uiState.flipOrientation();
    renderCurrentBoard();
}

function canSelectSource(coord, piece, gameState) {
    if (!piece) {
        flashSelectionError('Select a square with a piece.');
        return false;
    }
    if (gameState.gameStatus === 'finished') {
        flashSelectionError('Game is finished. The final position is locked.');
        return false;
    }
    if (!Array.isArray(gameState.legalMoves?.[coord]) || gameState.legalMoves[coord].length === 0) {
        flashSelectionError(`No server legal moves are available from ${coord}.`);
        return false;
    }

    return true;
}

function needsPromotionChoice(piece, to) {
    return piece?.[1] === 'p' && (to[1] === '8' || to[1] === '1');
}

function renderPromotionPanel(from, to) {
    elements.promotionPanel.hidden = false;
    elements.promotionPanel.querySelector('strong').textContent = `Promote ${from} to ${to}`;
    elements.promotionChoices.querySelector('button').focus();
}

function hidePromotionPanel() {
    elements.promotionPanel.hidden = true;
}

function handlePromotionChoice(event) {
    const button = event.target.closest('button[data-promotion]');
    if (!button || !pendingPromotionMove) {
        return;
    }

    submitMove({
        ...pendingPromotionMove,
        promotion: button.dataset.promotion,
    });
}

function cancelPromotion() {
    pendingPromotionMove = null;
    hidePromotionPanel();
    uiState.clearSelection();
    renderCurrentBoard();
    setStatus('Promotion cancelled.');
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

function lastMove(gameState) {
    const history = gameState?.moveHistory || [];

    return history[history.length - 1] || null;
}

function replayLastMove() {
    const replay = uiState.replay;
    if (!replay || replay.index <= 0) {
        return null;
    }

    return replay.moves[replay.index - 1] || null;
}

function checkedKing(gameState) {
    if (!gameState?.kingInCheck) {
        return null;
    }

    return findKingSquare(gameState.board || [], gameState.kingInCheck);
}

function renderCaptures() {
    const state = uiState.gameState;
    renderCaptureList(elements.capturedWhite, state?.capturedWhite || []);
    renderCaptureList(elements.capturedBlack, state?.capturedBlack || []);
}

function renderCaptureList(element, pieces) {
    element.replaceChildren();
    if (pieces.length === 0) {
        element.textContent = 'None';
        return;
    }

    pieces.forEach((piece) => {
        const item = document.createElement('span');
        item.className = 'captured-piece';
        item.textContent = pieceLabel(piece);
        element.append(item);
    });
}

function renderBoardStatus() {
    elements.boardOrientation.textContent = `${uiState.orientation[0].toUpperCase() + uiState.orientation.slice(1)} at bottom`;

    const state = uiState.gameState;
    elements.activeColor.classList.toggle('terminal', state?.gameStatus === 'finished');
    if (state?.gameStatus === 'finished') {
        elements.activeColor.textContent = terminalLabel(state);
    }
}

function renderActionControls() {
    const state = uiState.gameState;
    const reviewing = uiState.isReviewing;
    const finished = state?.gameStatus === 'finished';
    const active = Boolean(state) && !reviewing && !finished;
    const drawOfferedBy = state?.drawOffer?.offeredBy || null;
    const canAcceptDraw = active && drawOfferedBy !== null;
    const canClaimDraw = active && Array.isArray(state?.availableActions) && state.availableActions.includes('claimDraw');

    elements.refresh.disabled = false;
    elements.reset.disabled = reviewing;
    elements.quickGuest.disabled = reviewing;
    elements.newGameForm.querySelectorAll('input, select, button').forEach((control) => {
        control.disabled = reviewing;
    });
    elements.flipBoard.disabled = false;
    elements.abandon.disabled = !active;
    elements.resign.disabled = !active;
    elements.offerDraw.disabled = !active;
    elements.acceptDraw.disabled = !canAcceptDraw;
    elements.claimDraw.disabled = !canClaimDraw;
    elements.soundToggle.disabled = false;
    elements.soundToggle.setAttribute('aria-pressed', audioFeedback.isEnabled() ? 'true' : 'false');
    elements.soundToggle.textContent = audioFeedback.isEnabled() ? 'Sound on' : 'Sound off';

    elements.resign.textContent = active ? `Resign ${capitalize(state.activeColor || 'side')}` : 'Resign';
    elements.offerDraw.textContent = active ? `Offer draw as ${capitalize(state.activeColor || 'side')}` : 'Offer draw';
    elements.acceptDraw.textContent = canAcceptDraw ? `Accept ${capitalize(drawOfferedBy)} offer` : 'Accept draw';
    elements.claimDraw.textContent = canClaimDraw ? `Claim ${claimLabel(state.drawClaims?.[0])}` : 'Claim draw';
}

function renderDrawOffer() {
    const offeredBy = uiState.gameState?.drawOffer?.offeredBy || null;
    elements.drawOfferNotice.hidden = offeredBy === null || uiState.isReviewing;
    if (offeredBy !== null) {
        elements.drawOfferNotice.textContent = `${capitalize(offeredBy)} offered a draw. The opponent may accept.`;
    }
}

function renderTerminalSummary() {
    const state = uiState.gameState;
    const reason = state?.terminationReason || null;
    const finished = state?.gameStatus === 'finished';
    elements.terminalSummary.hidden = !finished || uiState.isReviewing;
    elements.terminalSummary.dataset.reason = finished && reason ? reason : '';

    if (!finished) {
        elements.terminalTitle.textContent = 'Game finished';
        elements.terminalDetail.textContent = '';
        return;
    }

    const copy = TERMINAL_COPY[reason] || ['Game finished', 'The server recorded a final state.'];
    const result = state.result ? ` Result: ${state.result}.` : '';
    elements.terminalTitle.textContent = copy[0];
    elements.terminalDetail.textContent = `${copy[1]}${result}`;
}

function startClockRendering() {
    if (clockIntervalId !== null) {
        window.clearInterval(clockIntervalId);
        clockIntervalId = null;
    }

    renderClocks();

    const clock = uiState.gameState?.clockState;
    if (!uiState.isReviewing && clock?.mode === 'timed' && uiState.gameState?.gameStatus !== 'finished') {
        clockIntervalId = window.setInterval(renderClocks, 250);
    }
}

function renderClocks() {
    const state = uiState.gameState;
    const clock = state?.clockState || {};
    const participants = state?.participants || {};
    elements.whiteClockLabel.textContent = participants.white?.label || 'White';
    elements.blackClockLabel.textContent = participants.black?.label || 'Black';

    if (uiState.isReviewing) {
        setClockFace('white', 'Review', false);
        setClockFace('black', 'Review', false);
        return;
    }

    if (clock.mode !== 'timed') {
        setClockFace('white', 'Untimed', false);
        setClockFace('black', 'Untimed', false);
        return;
    }

    const activeColor = state?.gameStatus === 'finished' ? null : clock.activeColor;
    setClockFace('white', formatClock(projectRemaining(clock, 'white')), activeColor === 'white');
    setClockFace('black', formatClock(projectRemaining(clock, 'black')), activeColor === 'black');
}

function setClockFace(color, text, active) {
    const face = color === 'white' ? elements.whiteClock : elements.blackClock;
    const time = color === 'white' ? elements.whiteClockTime : elements.blackClockTime;
    face.classList.toggle('active', active);
    face.classList.toggle('low-time', text !== 'Untimed' && text !== 'Review' && clockMillisecondsFromText(text) <= 20_000);
    time.textContent = text;
}

function projectRemaining(clock, color) {
    const key = `${color}RemainingMilliseconds`;
    const remaining = Number(clock[key]);
    if (!Number.isFinite(remaining)) {
        return null;
    }
    if (clock.activeColor !== color || uiState.gameState?.gameStatus === 'finished') {
        return Math.max(0, remaining);
    }

    const startedAt = Number(clock.turnStartedAtMilliseconds);
    if (!Number.isFinite(startedAt)) {
        return Math.max(0, remaining);
    }

    return Math.max(0, remaining - Math.max(0, Date.now() - startedAt));
}

function formatClock(milliseconds) {
    if (milliseconds === null) {
        return '--';
    }

    if (milliseconds < 20_000) {
        const totalTenths = Math.floor(milliseconds / 100);
        const minutes = Math.floor(totalTenths / 600);
        const seconds = Math.floor((totalTenths % 600) / 10);
        const tenths = totalTenths % 10;

        return `${minutes}:${String(seconds).padStart(2, '0')}.${tenths}`;
    }

    const totalSeconds = Math.ceil(milliseconds / 1000);
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;

    return `${minutes}:${String(seconds).padStart(2, '0')}`;
}

function clockMillisecondsFromText(value) {
    const match = value.match(/^(\d+):(\d{2})(?:\.(\d))?$/);
    if (!match) {
        return Number.POSITIVE_INFINITY;
    }

    return (Number(match[1]) * 60 + Number(match[2])) * 1000 + Number(match[3] || 0) * 100;
}

function actionPayload(action) {
    const state = uiState.gameState;
    if (!state) {
        setActionMessage('No live game state is loaded.');
        return null;
    }

    if (action === 'acceptDraw') {
        const offeredBy = state.drawOffer?.offeredBy || null;
        if (!offeredBy) {
            setActionMessage('No draw offer is available.');
            return null;
        }

        return { actorColor: offeredBy === 'white' ? 'black' : 'white' };
    }

    const actorColor = state.activeColor;
    if (actorColor !== 'white' && actorColor !== 'black') {
        setActionMessage('No active side is available for this action.');
        return null;
    }

    if (action === 'claimDraw') {
        return { actorColor, claim: state.drawClaims?.[0] || null };
    }

    return { actorColor };
}

function actionRequest(action, payload) {
    if (action === 'abandon') {
        return api.abandonGame(payload);
    }
    if (action === 'resign') {
        return api.resignGame(payload);
    }
    if (action === 'offerDraw') {
        return api.offerDraw(payload);
    }
    if (action === 'acceptDraw') {
        return api.acceptDraw(payload);
    }
    if (action === 'claimDraw') {
        return api.claimDraw(payload);
    }

    return Promise.reject(new Error(`Unsupported action: ${action}`));
}

function actionLabel(action) {
    return splitWords(action).replace(/^\w/, (letter) => letter.toUpperCase());
}

function toggleSound() {
    const enabled = audioFeedback.toggle();
    renderActionControls();
    setActionMessage(enabled ? 'Sound enabled.' : 'Sound disabled.');
}

function playStateFeedback(previousState, nextState, source) {
    if (!nextState || !source) {
        return;
    }

    const kind = feedbackKind(previousState, nextState, source);
    if (!kind) {
        return;
    }

    audioFeedback.play(kind);
    flashBoardFeedback(kind);
}

function feedbackKind(previousState, nextState, source) {
    if (nextState.gameStatus === 'finished' && previousState?.gameStatus !== 'finished') {
        return 'gameEnd';
    }
    if (source !== 'move') {
        return null;
    }
    if (nextState.kingInCheck) {
        return 'check';
    }
    if (capturedPieceCount(nextState) > capturedPieceCount(previousState)) {
        return 'capture';
    }
    if ((nextState.moveHistory?.length || 0) > (previousState?.moveHistory?.length || 0)) {
        return 'move';
    }

    return null;
}

function capturedPieceCount(state) {
    return (state?.capturedWhite?.length || 0) + (state?.capturedBlack?.length || 0);
}

function flashBoardFeedback(kind) {
    if (!elements.boardPanel) {
        return;
    }

    if (feedbackTimeoutId !== null) {
        window.clearTimeout(feedbackTimeoutId);
    }

    elements.boardPanel.classList.remove('feedback-move', 'feedback-capture', 'feedback-check', 'feedback-game-end');
    elements.boardPanel.classList.add('feedback-active', feedbackClass(kind));
    feedbackTimeoutId = window.setTimeout(() => {
        elements.boardPanel.classList.remove(
            'feedback-active',
            'feedback-move',
            'feedback-capture',
            'feedback-check',
            'feedback-game-end',
        );
        feedbackTimeoutId = null;
    }, 420);
}

function feedbackClass(kind) {
    return kind === 'gameEnd' ? 'feedback-game-end' : `feedback-${kind}`;
}

function terminalLabel(state) {
    const reason = state.terminationReason ? ` by ${splitWords(state.terminationReason)}` : '';
    const result = state.result ? ` (${state.result})` : '';

    return `Finished${result}${reason}`;
}

function claimLabel(claim) {
    if (claim === 'fiftyMoveRule') {
        return '50-move draw';
    }
    if (claim === 'threefoldRepetition') {
        return 'threefold draw';
    }

    return 'draw';
}

function capitalize(value) {
    return value.charAt(0).toUpperCase() + value.slice(1);
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

function splitWords(value) {
    return value.replace(/([a-z])([A-Z])/g, '$1 $2').toLowerCase();
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
