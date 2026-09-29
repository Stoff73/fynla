// The one reader for a Fyn chat stream (Rule 20), shared by the web store and
// the /m transport the way renderFynText is. Every Fyn endpoint streams
// `data: {json}\n\n` frames; the server ends every turn with exactly one
// terminal frame (AiChatController::streamTurn). A stream that closes without
// one was cut off mid-turn — a PHP fatal, a proxy or execution timeout, a
// dropped connection — and the caller must say so and offer to ask again
// rather than leave a half-written reply standing as the answer (L3-2,
// conversation 940 on fynla.org, 29 Sep 2026).

// `resume` answers /onboarding/start for a user already mid-flow: it is the
// whole response, so it is terminal too.
export const FYN_TERMINAL_EVENTS = Object.freeze(['done', 'error', 'token_limit', 'consent_required', 'resume']);

// One frame-consumer per stream: parses a `data:` line, records the first
// terminal frame, and hands the frame to onEvent (awaited, so an async handler
// finishes before the next frame). A malformed line, or an onEvent that
// throws, skips that one frame, never the rest of the stream.
function frameConsumer(onEvent) {
  const state = { terminal: null };
  const consume = async (line) => {
    if (!line.startsWith('data: ')) return;
    let event;
    try {
      event = JSON.parse(line.slice(6));
    } catch {
      return;
    }
    if (state.terminal === null && FYN_TERMINAL_EVENTS.includes(event?.type)) state.terminal = event.type;
    try {
      await onEvent(event);
    } catch {
      // One frame's handler failing must not abandon the rest of the turn.
    }
  };
  return { state, consume };
}

// Read frames from a fetch body reader until the stream closes. Frames after
// `done` (the gamification `level_up`) are still delivered. Resolves with the
// first terminal frame type seen, or null when the stream closed without one.
export async function readFynEvents(reader, onEvent) {
  const { state, consume } = frameConsumer(onEvent);
  const decoder = new TextDecoder();
  let buffer = '';

  while (true) {
    const { done, value } = await reader.read();
    if (done) break;
    buffer += decoder.decode(value, { stream: true });
    const lines = buffer.split('\n');
    buffer = lines.pop() || '';
    for (const line of lines) await consume(line);
  }
  buffer += decoder.decode();
  if (buffer) await consume(buffer);

  return { terminal: state.terminal };
}

// The same frames from a body that was read in one go (older WebViews with no
// streaming body).
export async function parseFynEvents(raw, onEvent) {
  const { state, consume } = frameConsumer(onEvent);
  for (const line of String(raw || '').split('\n')) await consume(line);
  return { terminal: state.terminal };
}

// A connection dropped mid-turn: fetch, or the body reader part-way through
// the stream, rejects with a TypeError (Chrome logs it as net::ERR_ABORTED).
// That is a cut-off turn like a stream that closes early, so it gets the same
// message and retry. An HTTP error status is not: the transports throw a plain
// Error for those, and the caller keeps its own message.
export function isDroppedConnection(error) {
  return error instanceof TypeError;
}

// What every surface says when a turn was cut off.
export const FYN_INTERRUPTED_MESSAGE = 'Sorry, my reply was cut off before I finished. Please try again.';
