#!/usr/bin/env bash

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PORT="${1:-18180}"
DEBUG_PORT="${BROWSER_SMOKE_DEBUG_PORT:-$((PORT + 1000))}"
TEMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/php-solo-chess-browser-smoke.XXXXXX")"
SERVER_PID=""
CHROME_PID=""

cleanup() {
  if [[ -n "$CHROME_PID" ]]; then
    kill "$CHROME_PID" >/dev/null 2>&1 || true
    wait "$CHROME_PID" 2>/dev/null || true
  fi
  if [[ -n "$SERVER_PID" ]]; then
    kill "$SERVER_PID" >/dev/null 2>&1 || true
    wait "$SERVER_PID" 2>/dev/null || true
  fi
  rm -rf "$TEMP_DIR"
}
trap cleanup EXIT INT TERM

for command in php node curl jq; do
  if ! command -v "$command" >/dev/null 2>&1; then
    echo "Missing required command: $command" >&2
    exit 12
  fi
done

case "$PORT" in
  ''|*[!0-9]*) echo "Port must be a number: $PORT" >&2; exit 14 ;;
esac

find_chrome() {
  if [[ -n "${BROWSER_SMOKE_CHROME:-}" && -x "${BROWSER_SMOKE_CHROME:-}" ]]; then
    printf '%s\n' "$BROWSER_SMOKE_CHROME"
    return
  fi

  for candidate in chromium google-chrome chrome; do
    if command -v "$candidate" >/dev/null 2>&1; then
      command -v "$candidate"
      return
    fi
  done

  if [[ -x "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" ]]; then
    printf '%s\n' "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
    return
  fi

  return 1
}

CHROME_BIN="$(find_chrome)" || {
  echo "Missing Chrome or Chromium for browser smoke coverage." >&2
  exit 12
}

mkdir -p "$TEMP_DIR/sessions" "$TEMP_DIR/chrome-profile"
BASE_URL="http://127.0.0.1:$PORT"
DEBUG_URL="http://127.0.0.1:$DEBUG_PORT"

SOLO_CHESS_DATABASE_PATH="$TEMP_DIR/solo-chess.sqlite" \
  php -d "session.save_path=$TEMP_DIR/sessions" -S "127.0.0.1:$PORT" -t "$ROOT" "$ROOT/scripts/router.php" \
  >"$TEMP_DIR/server.log" 2>&1 &
SERVER_PID=$!

SERVER_READY=false
for _attempt in 1 2 3 4 5; do
  if curl -fsS "$BASE_URL/backend/public/api/session.php" >/dev/null 2>&1; then
    SERVER_READY=true
    break
  fi
  sleep 1
done

if [[ "$SERVER_READY" != "true" ]]; then
  echo "Browser smoke PHP server did not become ready:" >&2
  cat "$TEMP_DIR/server.log" >&2
  exit 14
fi

"$CHROME_BIN" \
  --headless=new \
  --disable-gpu \
  --no-first-run \
  --no-default-browser-check \
  --remote-debugging-address=127.0.0.1 \
  --remote-debugging-port="$DEBUG_PORT" \
  --user-data-dir="$TEMP_DIR/chrome-profile" \
  "$BASE_URL/frontend/" >"$TEMP_DIR/chrome.log" 2>&1 &
CHROME_PID=$!

CHROME_READY=false
for _attempt in 1 2 3 4 5 6 7 8 9 10; do
  if curl -fsS "$DEBUG_URL/json/list" >/dev/null 2>&1; then
    CHROME_READY=true
    break
  fi
  sleep 1
done

if [[ "$CHROME_READY" != "true" ]]; then
  echo "Chrome DevTools endpoint did not become ready:" >&2
  cat "$TEMP_DIR/chrome.log" >&2
  exit 14
fi

BASE_URL="$BASE_URL" DEBUG_URL="$DEBUG_URL" node <<'NODE'
const baseUrl = process.env.BASE_URL;
const debugUrl = process.env.DEBUG_URL;

async function debugJson(path) {
    const response = await fetch(`${debugUrl}${path}`);
    if (!response.ok) {
        throw new Error(`DevTools ${path} failed with HTTP ${response.status}`);
    }

    return response.json();
}

async function connectPage() {
    const targets = await debugJson('/json/list');
    const page = targets.find((target) => target.type === 'page' && target.webSocketDebuggerUrl);
    if (!page) {
        throw new Error('No debuggable page target found.');
    }

    return new Promise((resolve, reject) => {
        const socket = new WebSocket(page.webSocketDebuggerUrl);
        const callbacks = new Map();
        const listeners = [];
        let nextId = 1;

        socket.addEventListener('open', () => {
            resolve({
                send(method, params = {}) {
                    const id = nextId;
                    nextId += 1;
                    socket.send(JSON.stringify({ id, method, params }));

                    return new Promise((resolveCommand, rejectCommand) => {
                        callbacks.set(id, { resolve: resolveCommand, reject: rejectCommand, method });
                    });
                },
                waitForEvent(method, timeoutMs = 5000) {
                    return new Promise((resolveEvent, rejectEvent) => {
                        const timer = setTimeout(() => {
                            rejectEvent(new Error(`Timed out waiting for ${method}`));
                        }, timeoutMs);
                        listeners.push({ method, resolve: resolveEvent, timer });
                    });
                },
                close() {
                    socket.close();
                },
            });
        });

        socket.addEventListener('message', (event) => {
            const message = JSON.parse(event.data);
            if (message.id && callbacks.has(message.id)) {
                const callback = callbacks.get(message.id);
                callbacks.delete(message.id);
                if (message.error) {
                    callback.reject(new Error(`${callback.method} failed: ${message.error.message}`));
                } else {
                    callback.resolve(message.result || {});
                }

                return;
            }

            if (message.method) {
                for (let index = listeners.length - 1; index >= 0; index -= 1) {
                    const listener = listeners[index];
                    if (listener.method === message.method) {
                        clearTimeout(listener.timer);
                        listeners.splice(index, 1);
                        listener.resolve(message.params || {});
                    }
                }
            }
        });

        socket.addEventListener('error', () => reject(new Error('DevTools WebSocket failed.')));
    });
}

async function main() {
    const page = await connectPage();
    await page.send('Runtime.enable');
    await page.send('Page.enable');
    await page.send('Emulation.setDeviceMetricsOverride', {
        width: 1280,
        height: 900,
        deviceScaleFactor: 1,
        mobile: false,
    });
    const loaded = page.waitForEvent('Page.loadEventFired', 10000);
    await page.send('Page.navigate', { url: `${baseUrl}/frontend/` });
    await loaded;

    await waitUntil(page, 'initial board render', `
        document.readyState === 'complete'
            && document.querySelectorAll('#chessBoard .square').length === 64
            && document.querySelector('#activeColor')?.textContent.includes('WHITE')
    `);

    await click(page, '#soundToggleButton');
    await waitUntil(page, 'persisted sound toggle', `
        localStorage.getItem('soloChess.soundEnabled') === 'true'
            && document.querySelector('#soundToggleButton')?.textContent.includes('on')
    `);

    await click(page, '#quickGuestButton');
    await waitUntil(page, 'guest game ready', `
        document.querySelector('#activeColor')?.textContent.includes('WHITE')
            && document.querySelector('#whiteClockTime')?.textContent === 'Untimed'
    `);
    await click(page, '[data-coord="e2"]');
    await click(page, '[data-coord="e4"]');
    await waitUntil(page, 'guest move through board UI', `
        document.querySelector('#activeColor')?.textContent.includes('BLACK')
            && document.querySelectorAll('#moveHistory li').length >= 1
    `);

    const username = `smoke${Date.now()}`;
    await evaluate(page, `
        (() => {
            const form = document.querySelector('#registerForm');
            form.elements.username.value = ${JSON.stringify(username)};
            form.elements.displayName.value = 'Smoke Player';
            form.elements.password.value = 'correct horse';
            form.requestSubmit();
            return true;
        })()
    `);
    await waitUntil(page, 'local account registration', `
        document.querySelector('#accountBadge')?.textContent.includes('Smoke Player')
    `);

    await evaluate(page, `
        (() => {
            const form = document.querySelector('#newGameForm');
            form.querySelector('input[name="timeKind"][value="preset"]').checked = true;
            form.elements.timePreset.value = '1+0';
            form.requestSubmit();
            return true;
        })()
    `);
    await waitUntil(page, 'timed game status', `
        document.querySelector('#whiteClockTime')?.textContent !== 'Untimed'
            && document.querySelector('#blackClockTime')?.textContent !== 'Untimed'
            && document.querySelectorAll('.clock-face.active').length === 1
    `);
    await click(page, '[data-coord="e2"]');
    await click(page, '[data-coord="e4"]');
    await waitUntil(page, 'timed move accepted', `
        document.querySelector('#activeColor')?.textContent.includes('BLACK')
            && document.querySelectorAll('#moveHistory li').length >= 1
    `);
    await click(page, '#resignButton');
    await waitUntil(page, 'terminal feedback visible', `
        !document.querySelector('#terminalSummary')?.hidden
            && document.querySelector('#terminalSummary')?.textContent.includes('Resignation')
    `);

    await click(page, '#refreshHistoryButton');
    await waitUntil(page, 'personal history listed', `
        document.querySelectorAll('#savedGames li').length >= 1
            && document.querySelector('#savedGames button[data-action="replay"]')
    `);
    await click(page, '#savedGames button[data-action="replay"]');
    await waitUntil(page, 'saved replay mode', `
        !document.querySelector('#reviewBanner')?.hidden
            && !document.querySelector('#replayControls')?.hidden
    `);
    await click(page, '#replayNextButton');
    await waitUntil(page, 'replay navigation', `
        document.querySelector('#reviewStepLabel')?.textContent.includes('Step')
    `);

    await click(page, '#logoutButton');
    await waitUntil(page, 'account logout navigation', `
        document.querySelector('#accountBadge')?.textContent.includes('Guest session')
    `);
    await evaluate(page, `
        (() => {
            const form = document.querySelector('#loginForm');
            form.elements.username.value = ${JSON.stringify(username)};
            form.elements.password.value = 'correct horse';
            form.requestSubmit();
            return true;
        })()
    `);
    await waitUntil(page, 'account login navigation', `
        document.querySelector('#accountBadge')?.textContent.includes('Smoke Player')
    `);

    await page.send('Emulation.setDeviceMetricsOverride', {
        width: 390,
        height: 844,
        deviceScaleFactor: 1,
        mobile: true,
    });
    await evaluate(page, 'window.dispatchEvent(new Event("resize")); true');
    await waitUntil(page, 'mobile layout without horizontal overflow', `
        document.querySelectorAll('#chessBoard .square').length === 64
            && document.documentElement.scrollWidth <= window.innerWidth + 1
            && document.querySelector('#chessBoard').getBoundingClientRect().width <= window.innerWidth
    `);

    page.close();
}

async function click(page, selector) {
    await evaluate(page, `
        (() => {
            const element = document.querySelector(${JSON.stringify(selector)});
            if (!element) {
                throw new Error('Missing selector: ${selector}');
            }
            element.click();
            return true;
        })()
    `);
}

async function waitUntil(page, label, expression, timeoutMs = 10000) {
    const startedAt = Date.now();
    while (Date.now() - startedAt < timeoutMs) {
        if (await evaluate(page, `Boolean(${expression})`)) {
            return;
        }
        await new Promise((resolve) => setTimeout(resolve, 100));
    }

    throw new Error(`Timed out waiting for ${label}`);
}

async function evaluate(page, expression) {
    const result = await page.send('Runtime.evaluate', {
        expression,
        awaitPromise: true,
        returnByValue: true,
    });
    if (result.exceptionDetails) {
        throw new Error(result.exceptionDetails.text || 'Runtime evaluation failed.');
    }

    return result.result?.value;
}

main().catch((error) => {
    process.stderr.write(`${error.stack || error.message}\n`);
    process.exit(1);
});
NODE

echo "Browser smoke passed."
