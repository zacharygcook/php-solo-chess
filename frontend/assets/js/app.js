import { createApiClient } from './api.js';
import { getPieceAt, renderBoard } from './board.js';
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
    elements.refresh = document.querySelector('#refreshButton');
    elements.reset = document.querySelector('#resetButton');
    elements.fenForm = document.querySelector('#fenForm');
    elements.fenInput = document.querySelector('#fenInput');
    elements.header = document.querySelector('.app-header');
}

function bindEvents() {
    elements.refresh.addEventListener('click', loadState);
    elements.reset.addEventListener('click', resetGame);
    elements.fenForm.addEventListener('submit', (event) => {
        event.preventDefault();
        submitFen();
    });
}

async function loadState() {
    setStatus('Loading session...');

    try {
        applyState(await api.loadSession());
    } catch (_error) {
        setStatus('Failed to reach backend session endpoint.');
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

function applyState(response) {
    if (!response || !response.state) {
        setStatus('Malformed response from backend.');
        return;
    }

    uiState.setGameState(response.state);
    renderCurrentBoard();
    renderHistory(response.state.moveHistory || []);
    setStatus(response.message || 'Ready.');

    const active = (response.state.activeColor || '').toUpperCase();
    elements.activeColor.textContent = active ? `${active} to move` : '-';
    elements.activeMoveLabel.textContent = active || '-';
}

function renderCurrentBoard() {
    const gameState = uiState.gameState;
    renderBoard(elements.board, gameState?.board || [], uiState.selection, handleSquareAction);
    handleResize();
}

function renderHistory(moves) {
    elements.history.replaceChildren();

    moves.forEach((move, index) => {
        const item = document.createElement('li');
        item.textContent = `${index + 1}. ${move.from || '??'} to ${move.to || '??'}`;
        elements.history.append(item);
    });
}

function setStatus(message) {
    elements.status.textContent = message;
}

function flashSelectionError(message) {
    setStatus(message);
    window.setTimeout(() => {
        setStatus('');
    }, 2000);
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
