# Relevant context

- `SPEC.md` sections 3–5 and its play/history/timed journeys define setup, interaction, responsive,
  sound, review, clock, and terminal-feedback requirements.
- The Technology constraint requires removing jQuery and using vanilla JavaScript/CSS without a
  framework or bundler.
- `frontend/index.html`, `frontend/assets/js/app.js`, and `frontend/assets/css/styles.css` are the
  current presentation surface.
- Backend legal moves are authoritative; client hints are advisory and illegal attempts must leave
  server state unchanged.
- Sprint 3 APIs provide account, game lifecycle, history, replay, and clock state consumed here.
- Audio must be small, local, optional, and never delay persistence or obscure the final position.
