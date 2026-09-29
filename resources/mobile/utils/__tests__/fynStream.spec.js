import { describe, it, expect, vi } from 'vitest';
import { isDroppedConnection, parseFynEvents, readFynEvents } from '../fynStream.js';

// One reader for every Fyn stream on web and /m (L3-2, 29 Sep 2026): a turn is
// finished only when a terminal frame arrived.
function reader(chunks) {
  const queue = chunks.map((c) => new TextEncoder().encode(c));
  return {
    read: vi.fn(async () => (queue.length
      ? { done: false, value: queue.shift() }
      : { done: true, value: undefined })),
  };
}

const frame = (event) => `data: ${JSON.stringify(event)}\n\n`;

describe('readFynEvents', () => {
  it('reports the terminal frame and delivers frames that follow it', async () => {
    const seen = [];
    const { terminal } = await readFynEvents(reader([
      frame({ type: 'content', text: 'Hello' }),
      frame({ type: 'done', message_id: 1 }),
      frame({ type: 'level_up', level: 2 }),
    ]), (e) => seen.push(e.type));

    expect(terminal).toBe('done');
    expect(seen).toEqual(['content', 'done', 'level_up']);
  });

  it('reports null for a stream cut off after the preamble', async () => {
    const { terminal } = await readFynEvents(reader([
      frame({ type: 'thinking' }),
      frame({ type: 'content', text: "I'll fetch the latest tax information." }),
    ]), () => {});

    expect(terminal).toBeNull();
  });

  it.each(['error', 'token_limit', 'consent_required', 'resume'])('treats %s as the end of the turn', async (type) => {
    const { terminal } = await readFynEvents(reader([frame({ type })]), () => {});
    expect(terminal).toBe(type);
  });

  it('joins a frame split across chunks and reads a final frame with no trailing newline', async () => {
    const seen = [];
    const whole = frame({ type: 'content', text: 'split' });
    const { terminal } = await readFynEvents(reader([
      whole.slice(0, 10),
      whole.slice(10),
      'data: {"type":"done"}',
    ]), (e) => seen.push(e.type));

    expect(seen).toEqual(['content', 'done']);
    expect(terminal).toBe('done');
  });

  it('skips a malformed line or a failing handler without losing the rest of the turn', async () => {
    const seen = [];
    const { terminal } = await readFynEvents(reader([
      'data: {not json}\n\n',
      frame({ type: 'content', text: 'boom' }),
      frame({ type: 'done' }),
    ]), (e) => {
      seen.push(e.type);
      if (e.type === 'content') throw new Error('handler failed');
    });

    expect(seen).toEqual(['content', 'done']);
    expect(terminal).toBe('done');
  });

  it('waits for an async handler before the next frame', async () => {
    const order = [];
    await readFynEvents(reader([frame({ type: 'resume' }), frame({ type: 'content' })]), async (e) => {
      if (e.type === 'resume') await new Promise((r) => { setTimeout(r, 5); });
      order.push(e.type);
    });

    expect(order).toEqual(['resume', 'content']);
  });
});

describe('parseFynEvents', () => {
  it('reads a body delivered in one go', async () => {
    const seen = [];
    const { terminal } = await parseFynEvents(
      frame({ type: 'content', text: 'a' }) + frame({ type: 'done' }),
      (e) => seen.push(e.type),
    );

    expect(seen).toEqual(['content', 'done']);
    expect(terminal).toBe('done');
  });

  it('reports null when the one-shot body has no terminal frame', async () => {
    expect((await parseFynEvents(frame({ type: 'content', text: 'a' }), () => {})).terminal).toBeNull();
  });
});

describe('isDroppedConnection', () => {
  it('is a fetch or body-reader network failure, not an HTTP error', () => {
    expect(isDroppedConnection(new TypeError('network error'))).toBe(true);
    expect(isDroppedConnection(new Error('Chat request failed: 500'))).toBe(false);
    expect(isDroppedConnection(null)).toBe(false);
  });
});
