import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/services/aiChatService', () => ({
  default: {
    postActionStream: vi.fn(),
  },
}));

import aiChatService from '@/services/aiChatService';
import aiChat from '@/store/modules/aiChat';

// Walked on csjones 2026-10-05 (user 480): tapping a record under "Which one
// needs changing?" did nothing on web. The store kept its own list of five
// actions and dropped "edit:<type>:<id>"; the server (AiChatController::action)
// and /m accept the record and section choices too.
function harness() {
  const localState = { ...aiChat.state, currentConversation: { id: 10, title: 'Fyn' }, messages: [], streamingText: '', error: null };
  const commit = (name, payload) => aiChat.mutations[name](localState, payload);
  const context = { commit, dispatch: vi.fn().mockResolvedValue(undefined), state: localState, rootState: { route: { path: '/dashboard' } } };
  return { context };
}

describe('Fyn action bubbles on web', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    aiChatService.postActionStream.mockResolvedValue({ read: vi.fn(async () => ({ done: true, value: undefined })) });
  });

  it.each(['edit:savings_account:12', 'edit_section:savings', 'continue'])('posts %s to the server', async (action) => {
    await aiChat.actions.postAction(harness().context, action);

    expect(aiChatService.postActionStream).toHaveBeenCalledWith(10, action, expect.any(Object));
  });

  it('still refuses an action the server does not take', async () => {
    await aiChat.actions.postAction(harness().context, 'delete_everything');

    expect(aiChatService.postActionStream).not.toHaveBeenCalled();
  });
});
