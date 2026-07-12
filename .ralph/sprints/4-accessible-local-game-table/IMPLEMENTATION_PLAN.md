# Implementation plan

1. Remove jQuery and establish a small vanilla-JavaScript API/state/render boundary.
2. Build registration/login, game creation, personal history, and saved-game/replay navigation.
3. Implement keyboard/click/tap and drag/drop board interaction with advisory legal destinations and
   accessible selection, promotion, orientation, capture, check, and last-move presentation.
4. Present server clocks and lifecycle actions with responsive status, non-disruptive errors, and
   distinct terminal states at laptop and mobile sizes.
5. Add local lightweight sound and brief non-blocking feedback, persist the preference, and expand
   browser-facing smoke coverage.

Frontend tests verify presentation and intent wiring; backend/domain tests remain the authority for
legality. Every chunk is independently validated, committed, and handed off through the scratchpad.
