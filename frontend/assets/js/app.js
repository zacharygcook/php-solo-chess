(function ($) {
    const API_BASE = $('body').data('api-base') || '/api';

    const PIECE_SYMBOLS = {
        wp: '♙', wr: '♖', wn: '♘', wb: '♗', wq: '♕', wk: '♔',
        bp: '♟︎', br: '♜', bn: '♞', bb: '♝', bq: '♛', bk: '♚',
    };

    const SoloChessApp = {
        state: null,
        selection: { from: null, to: null },
        init() {
            this.cacheDom();
            this.bindEvents();
            this.handleResize = this.handleResize.bind(this);
            this.handleResize();
            this.$window.on('resize', this.handleResize);
            this.loadState();
        },
        cacheDom() {
            this.$board = $('#chessBoard');
            this.$history = $('#moveHistory');
            this.$status = $('#statusMessage');
            this.$activeColor = $('#activeColor');
            this.$activeMoveLabel = $('#activeMoveLabel');
            this.$refresh = $('#refreshButton');
            this.$reset = $('#resetButton');
            this.$fenForm = $('#fenForm');
            this.$fenInput = $('#fenInput');
            this.$window = $(window);
            this.$header = $('.app-header');
        },
        bindEvents() {
            this.$refresh.on('click', () => this.loadState());
            this.$reset.on('click', () => this.resetGame());
            this.$fenForm.on('submit', (event) => {
                event.preventDefault();
                this.submitFen();
            });
        },
        loadState() {
            this.setStatus('Loading session...');
            $.getJSON(`${API_BASE}/session.php`)
                .done((response) => {
                    this.applyState(response);
                })
                .fail(() => {
                    this.setStatus('Failed to reach backend session endpoint.');
                });
        },
        resetGame() {
            this.setStatus('Resetting session...');
            $.ajax({
                url: `${API_BASE}/reset.php`,
                method: 'POST',
                contentType: 'application/json',
            })
                .done((response) => {
                    this.selection = { from: null, to: null };
                    this.applyState(response);
                })
                .fail(() => {
                    this.setStatus('Reset failed. Check backend logs.');
                });
        },
        submitFen() {
            const fen = this.$fenInput.val().trim();
            if (!fen) {
                this.setStatus('Provide a FEN string first.');
                return;
            }

            this.setStatus('Sending FEN placeholder request...');
            $.ajax({
                url: `${API_BASE}/setup.php`,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ fen }),
            })
                .done((response) => {
                    this.applyState(response);
                })
                .fail(() => {
                    this.setStatus('FEN request failed.');
                });
        },
        handleSquareClick(coord) {
            if (!this.state) {
                return;
            }

            const piece = this.getPieceAt(coord);

            if (!this.selection.from) {
                if (!piece) {
                    this.flashSelectionError('Select a square with a piece.');
                    return;
                }
                console.log("From coordinate: ", coord);
                this.selection.from = coord;
            } else if (this.selection.from && !this.selection.to) {
                if (this.selection.from === coord) {
                    this.selection.from = null;
                } else {
                    console.log("To coordinate: ", coord);
                    this.selection.to = coord;
                    this.submitMove();
                }
            }

            this.renderBoard(this.state.board);
        },
        submitMove() {
            if (!this.selection.from || !this.selection.to) {
                return;
            }

            const payload = {
                from: this.selection.from,
                to: this.selection.to,
            };

            this.setStatus(`Submitting move ${payload.from} → ${payload.to}...`);

            $.ajax({
                url: `${API_BASE}/move.php`,
                method: 'POST',
                data: JSON.stringify(payload),
                contentType: 'application/json',
            })
                .done((response) => {
                    this.applyState(response);
                })
                .fail(() => {
                    this.setStatus('Move request failed.');
                })
                .always(() => {
                    this.selection = { from: null, to: null };
                });
        },
        applyState(response) {
            if (!response || !response.state) {
                this.setStatus('Malformed response from backend.');
                return;
            }

            this.state = response.state;
            this.renderBoard(this.state.board || []);
            this.renderHistory(this.state.moveHistory || []);
            this.setStatus(response.message || 'Ready.');
            const active = (this.state.activeColor || '').toUpperCase();
            this.$activeColor.text(active ? `${active} to move` : '—');
            this.$activeMoveLabel.text(active || '—');
        },
        renderBoard(board) {
            this.$board.empty();

            board.forEach((row, rowIndex) => {
                row.forEach((cell, colIndex) => {
                    const coord = this.indexToCoord(rowIndex, colIndex);
                    const isLight = (rowIndex + colIndex) % 2 === 0;
                    const $square = $('<div>', {
                        class: `square ${isLight ? 'light' : 'dark'}`,
                        role: 'gridcell',
                        'data-coord': coord,
                        tabindex: 0,
                    });

                    if (this.selection.from === coord) {
                        $square.addClass('selected');
                    }

                    if (cell) {
                        const pieceColor = cell.startsWith('w') ? 'white' : 'black';
                        const glyph = PIECE_SYMBOLS[cell] || cell.toUpperCase();
                        const $piece = $('<span>', {
                            class: `piece piece-${pieceColor}`,
                            text: glyph,
                        });
                        $square.append($piece);
                    }

                    if (rowIndex === 7) {
                        $square.append(
                            $('<span>', {
                                class: 'coord file-label',
                                text: String.fromCharCode(97 + colIndex),
                            })
                        );
                    }

                    if (colIndex === 7) {
                        $square.append(
                            $('<span>', {
                                class: 'coord rank-label',
                                text: 8 - rowIndex,
                            })
                        );
                    }

                    $square.on('click keypress', (event) => {
                        if (event.type === 'click' || event.key === 'Enter' || event.key === ' ') {
                            event.preventDefault();
                            this.handleSquareClick(coord);
                        }
                    });

                    this.$board.append($square);
                });
            });
            this.handleResize();
        },
        renderHistory(moves) {
            this.$history.empty();
            moves.forEach((move, index) => {
                const label = `${index + 1}. ${move.from || '??'} → ${move.to || '??'}`;
                this.$history.append(`<li>${label}</li>`);
            });
        },
        setStatus(message) {
            this.$status.text(message);
        },
        flashSelectionError(message) {
            this.setStatus(message);
            setTimeout(() => {
                this.setStatus('');
            }, 2000);
        },
        getPieceAt(coord) {
            if (!this.state || !this.state.board) {
                return null;
            }
            const { row, col } = this.coordToIndex(coord);
            return this.state.board[row]?.[col] ?? null;
        },
        indexToCoord(row, col) {
            const file = String.fromCharCode('a'.charCodeAt(0) + col);
            const rank = 8 - row;
            return `${file}${rank}`;
        },
        coordToIndex(coord) {
            const file = coord.charCodeAt(0) - 97;
            const rank = parseInt(coord[1], 10);
            return {
                row: 8 - rank,
                col: file,
            };
        },
        handleResize() {
            if (!this.$board) {
                return;
            }
            const headerHeight = this.$header?.outerHeight(true) || 0;
            const availableHeight = Math.max(260, this.$window.height() - headerHeight - 120);
            const panelWidth = this.$board.closest('.board-panel').width() || this.$board.width();
            const boardSize = Math.max(240, Math.min(panelWidth, availableHeight));
            this.$board.css({
                width: boardSize,
                height: boardSize,
            });
        },
    };

    $(function () {
        SoloChessApp.init();
    });
})(jQuery);
