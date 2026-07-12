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

export function renderBoard(boardElement, board, selection, onSquareAction) {
    boardElement.replaceChildren();

    board.forEach((row, rowIndex) => {
        row.forEach((cell, colIndex) => {
            const coord = indexToCoord(rowIndex, colIndex);
            const square = document.createElement('div');
            const isLight = (rowIndex + colIndex) % 2 === 0;
            square.className = `square ${isLight ? 'light' : 'dark'}`;
            square.setAttribute('role', 'gridcell');
            square.dataset.coord = coord;
            square.tabIndex = 0;

            if (selection.from === coord) {
                square.classList.add('selected');
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

            boardElement.append(square);
        });
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

function createPieceElement(piece) {
    const sprite = PIECE_SPRITES[piece];
    const pieceColor = piece.startsWith('w') ? 'white' : 'black';
    const pieceName = PIECE_NAMES[piece[1]] || 'Piece';

    if (!sprite) {
        const fallback = document.createElement('span');
        fallback.className = `piece piece-${pieceColor}`;
        fallback.textContent = piece.toUpperCase();

        return fallback;
    }

    const image = document.createElement('img');
    image.className = 'piece';
    image.src = `assets/img/${sprite}.svg`;
    image.alt = `${pieceColor} ${pieceName}`;
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
