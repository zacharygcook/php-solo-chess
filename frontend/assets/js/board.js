const PIECE_SPRITES = {
    wp: 'white-pawn',
    wr: 'white-rook',
    wn: 'white-knight',
    wb: 'white-bishop',
    wq: 'white-queen',
    wk: 'white-king',
    bp: 'black-pawn',
    br: 'black-rook',
    bn: 'black-knight',
    bb: 'black-bishop',
    bq: 'black-queen',
    bk: 'black-king',
};

const PIECE_NAMES = {
    p: 'Pawn',
    r: 'Rook',
    n: 'Knight',
    b: 'Bishop',
    q: 'Queen',
    k: 'King',
};

const PIECE_COLORS = {
    w: 'white',
    b: 'black',
};

const FEN_PIECES = {
    P: 'wp',
    R: 'wr',
    N: 'wn',
    B: 'wb',
    Q: 'wq',
    K: 'wk',
    p: 'bp',
    r: 'br',
    n: 'bn',
    b: 'bb',
    q: 'bq',
    k: 'bk',
};

export function renderBoard(boardElement, board, selection, onSquareAction, options = {}) {
    boardElement.replaceChildren();
    boardElement.dataset.orientation = options.orientation || 'white';
    boardElement.dataset.gameStatus = options.gameStatus || 'active';

    displayIndexes(options.orientation || 'white').forEach(({ rowIndex, colIndex }) => {
        const coord = indexToCoord(rowIndex, colIndex);
        const cell = board[rowIndex]?.[colIndex] ?? null;
        const square = document.createElement('div');
        const isLight = (rowIndex + colIndex) % 2 === 0;
        square.className = `square ${isLight ? 'light' : 'dark'}`;
        square.setAttribute('role', 'gridcell');
        square.setAttribute('aria-label', squareLabel(coord, cell, selection, options));
        square.dataset.coord = coord;
        square.tabIndex = 0;

        if (selection.from === coord) {
            square.classList.add('selected');
        }
        if (isLegalTarget(coord, selection, options.legalMoves)) {
            square.classList.add('target');
            if (cell) {
                square.classList.add('capture-target');
            }
        }
        if (isLegalSource(coord, options.legalMoves)) {
            square.classList.add('legal-source');
        }
        if (options.lastMove?.from === coord) {
            square.classList.add('last-move', 'last-move-from');
        }
        if (options.lastMove?.to === coord) {
            square.classList.add('last-move', 'last-move-to');
        }
        if (options.checkedKing === coord) {
            square.classList.add('checked-king');
        }
        if (options.gameStatus === 'finished') {
            square.classList.add('final-position');
        }

        if (cell) {
            square.append(createPieceElement(cell));
        }

        if (rowIndex === 7) {
            square.append(createCoordinateLabel('file-label', String.fromCharCode(97 + colIndex)));
        }

        if (colIndex === 7) {
            square.append(createCoordinateLabel('rank-label', String(8 - rowIndex)));
        }

        square.addEventListener('click', () => onSquareAction(coord));
        square.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                onSquareAction(coord);
            }
        });
        square.addEventListener('dragstart', (event) => {
            if (!isLegalSource(coord, options.legalMoves)) {
                event.preventDefault();
                return;
            }
            event.dataTransfer.setData('text/plain', coord);
            event.dataTransfer.effectAllowed = 'move';
            square.classList.add('dragging');
        });
        square.addEventListener('dragend', () => {
            square.classList.remove('dragging');
        });
        square.addEventListener('dragover', (event) => {
            if (event.dataTransfer.types.includes('text/plain')) {
                event.preventDefault();
            }
        });
        square.addEventListener('drop', (event) => {
            event.preventDefault();
            const from = event.dataTransfer.getData('text/plain');
            if (from && typeof options.onDrop === 'function') {
                options.onDrop(from, coord);
            }
        });
        square.draggable = isLegalSource(coord, options.legalMoves);

        boardElement.append(square);
    });
}

export function boardFromFen(fen) {
    if (typeof fen !== 'string' || fen.trim() === '') {
        return [];
    }

    const placement = fen.trim().split(/\s+/)[0];
    const rows = placement.split('/');
    if (rows.length !== 8) {
        return [];
    }

    const board = rows.map((row) => {
        const cells = [];
        [...row].forEach((token) => {
            if (/^[1-8]$/.test(token)) {
                for (let index = 0; index < Number(token); index += 1) {
                    cells.push(null);
                }
                return;
            }

            cells.push(FEN_PIECES[token] || null);
        });

        return cells;
    });

    return board.every((row) => row.length === 8) ? board : [];
}

export function getPieceAt(board, coord) {
    const { row, col } = coordToIndex(coord);

    return board[row]?.[col] ?? null;
}

export function findKingSquare(board, color) {
    const king = `${color[0]}k`;
    for (let row = 0; row < board.length; row += 1) {
        const col = board[row].indexOf(king);
        if (col !== -1) {
            return indexToCoord(row, col);
        }
    }

    return null;
}

export function pieceLabel(piece) {
    if (!piece) {
        return 'empty';
    }

    const color = PIECE_COLORS[piece[0]] || 'unknown';
    const pieceName = PIECE_NAMES[piece[1]] || 'Piece';

    return `${color} ${pieceName}`;
}

function createPieceElement(piece) {
    const sprite = PIECE_SPRITES[piece];
    const label = pieceLabel(piece);

    if (!sprite) {
        const fallback = document.createElement('span');
        fallback.className = `piece piece-${PIECE_COLORS[piece[0]] || 'unknown'}`;
        fallback.textContent = piece.toUpperCase();
        fallback.draggable = false;

        return fallback;
    }

    const image = document.createElement('img');
    image.className = 'piece';
    image.src = `assets/img/${sprite}.svg`;
    image.alt = label;
    image.draggable = false;

    return image;
}

function createCoordinateLabel(className, text) {
    const label = document.createElement('span');
    label.className = `coord ${className}`;
    label.textContent = text;

    return label;
}

function indexToCoord(row, col) {
    const file = String.fromCharCode('a'.charCodeAt(0) + col);
    const rank = 8 - row;

    return `${file}${rank}`;
}

function coordToIndex(coord) {
    const file = coord.charCodeAt(0) - 97;
    const rank = parseInt(coord[1], 10);

    return {
        row: 8 - rank,
        col: file,
    };
}

function displayIndexes(orientation) {
    const indexes = [];
    for (let row = 0; row < 8; row += 1) {
        for (let col = 0; col < 8; col += 1) {
            indexes.push({
                rowIndex: orientation === 'black' ? 7 - row : row,
                colIndex: orientation === 'black' ? 7 - col : col,
            });
        }
    }

    return indexes;
}

function isLegalSource(coord, legalMoves = {}) {
    return Array.isArray(legalMoves[coord]) && legalMoves[coord].length > 0;
}

function isLegalTarget(coord, selection, legalMoves = {}) {
    return Boolean(selection.from && legalMoves[selection.from]?.includes(coord));
}

function squareLabel(coord, piece, selection, options) {
    const labels = [`${coord}: ${pieceLabel(piece)}`];
    if (selection.from === coord) {
        labels.push('selected');
    }
    if (isLegalTarget(coord, selection, options.legalMoves)) {
        labels.push('legal destination');
    }
    if (options.lastMove?.from === coord) {
        labels.push('last move origin');
    }
    if (options.lastMove?.to === coord) {
        labels.push('last move destination');
    }
    if (options.checkedKing === coord) {
        labels.push('king in check');
    }
    if (options.gameStatus === 'finished') {
        labels.push('final position');
    }

    return labels.join(', ');
}
