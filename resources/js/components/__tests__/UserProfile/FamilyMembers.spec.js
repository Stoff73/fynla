import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import familyMembersService from '@/services/familyMembersService';
import FamilyMembers from '../../UserProfile/FamilyMembers.vue';

vi.mock('@/services/familyMembersService', () => ({
  default: {
    getFamilyMembers: vi.fn(),
    createFamilyMember: vi.fn(),
    updateFamilyMember: vi.fn(),
    deleteFamilyMember: vi.fn(),
  },
}));

describe('FamilyMembers.vue', () => {
  let wrapper;
  let store;

  // Jane is a LINKED spouse — an account really does sit behind this row, which
  // is what the card's "Account Linked" badge claims and why she has no Edit or
  // Delete. The fixture used to omit `is_linked_account`, so it described the
  // W-0051 orphan while the tests around it asserted the behaviour of a linked
  // account. Both facts have to be on the row now that the component reads the
  // link rather than the relationship string.
  const familyMembers = [
    {
      id: 1,
      name: 'Jane Doe',
      relationship: 'spouse',
      date_of_birth: '1992-05-15',
      gender: 'female',
      is_dependent: false,
      linked_user_id: 30,
      is_linked_account: true,
    },
    {
      id: 2,
      name: 'Johnny Doe',
      relationship: 'child',
      date_of_birth: '2015-08-20',
      gender: 'male',
      is_dependent: true,
      linked_user_id: null,
      is_linked_account: false,
    },
  ];

  /**
   * The row onboarding used to produce: relationship 'spouse', no account behind
   * it. It rendered as "Account Linked" with no controls, so a misspelt name was
   * permanent on every surface (W-0051).
   */
  const unlinkedSpouse = {
    id: 3,
    name: 'Arjun Raman',
    relationship: 'spouse',
    date_of_birth: '1977-06-02',
    gender: 'male',
    is_dependent: false,
    linked_user_id: null,
    is_linked_account: false,
  };

  // W-0132 — the recorded will, as `WillAnalysisService::charitableBequestSummary()`
  // publishes it on the profile this page already loads. Priya Raman's position when
  // the defect was raised: one £10,000 legacy to Cancer Research UK, on an account
  // whose `users.charitable_bequest` column was NULL. That column no longer exists
  // (W-0221); the will is the only record of the answer.
  const oneFixedLegacy = {
    has_bequests: true,
    count: 1,
    fixed_total: 10000,
    has_estate_share: false,
  };

  const createTestStore = (members = familyMembers, charitableBequests = oneFixedLegacy) => createStore({
    state: {
      aiFormFill: { pendingFill: null },
    },
    modules: {
      userProfile: {
        namespaced: true,
        state: () => ({
          familyMembers: structuredClone(members),
          profile: { charitable_bequests: charitableBequests },
        }),
        actions: {
          fetchProfile: vi.fn(() => Promise.resolve()),
          updatePersonalInfo: vi.fn(() => Promise.resolve()),
        },
      },
      auth: {
        namespaced: true,
        state: () => ({
          user: { marital_status: 'married', charitable_bequest: false },
        }),
        getters: {
          user: (state) => state.user,
        },
        actions: {
          fetchUser: vi.fn(() => Promise.resolve()),
        },
      },
      preview: {
        namespaced: true,
        getters: {
          isPreviewMode: () => false,
        },
      },
      spousePermission: {
        namespaced: true,
        actions: {
          fetchPermissionStatus: vi.fn(() => Promise.resolve()),
        },
      },
    },
  });

  const mountComponent = (testStore = store) => mount(FamilyMembers, {
    global: {
      plugins: [testStore],
      directives: {
        previewDisabled: {},
      },
      stubs: {
        FamilyMemberFormModal: {
          name: 'FamilyMemberFormModal',
          props: ['member'],
          emits: ['save', 'close'],
          template: '<div class="family-member-modal-stub" />',
        },
        ConfirmDialog: {
          name: 'ConfirmDialog',
          props: ['show', 'title', 'message'],
          emits: ['confirm', 'cancel'],
          template: '<div class="confirm-dialog-stub" />',
        },
        SpouseSuccessModal: {
          name: 'SpouseSuccessModal',
          props: ['show'],
          emits: ['close'],
          template: '<div class="spouse-success-stub" />',
        },
      },
    },
  });

  beforeEach(async () => {
    vi.clearAllMocks();
    familyMembersService.getFamilyMembers.mockResolvedValue({ data: { family_members: [] } });
    familyMembersService.createFamilyMember.mockResolvedValue({ data: {} });
    familyMembersService.updateFamilyMember.mockResolvedValue({ data: {} });
    familyMembersService.deleteFamilyMember.mockResolvedValue({ data: {} });

    store = createTestStore();
    wrapper = mountComponent();
    await flushPromises();
  });

  it('renders the family-members section', () => {
    expect(wrapper.findAll('h3').some((heading) => heading.text() === 'Family Members')).toBe(true);
    expect(wrapper.text()).toContain('Manage your family members and dependents');
  });

  it('displays the family members supplied by the profile store', () => {
    expect(wrapper.text()).toContain('Jane Doe');
    expect(wrapper.text()).toContain('Johnny Doe');
  });

  it('shows relationship and personal details', () => {
    const text = wrapper.text();
    expect(text).toContain('spouse');
    expect(text).toContain('child');
    expect(text).toContain('female');
    expect(text).toContain('male');
  });

  it('opens the add modal from the section action', async () => {
    const addButton = wrapper.findAll('button').find((button) => button.text() === 'Add');
    await addButton.trigger('click');

    expect(wrapper.vm.showModal).toBe(true);
    expect(wrapper.findComponent({ name: 'FamilyMemberFormModal' }).props('member')).toBeNull();
  });

  // Renamed from "only for editable non-spouse members". The relationship was
  // never the rule — it stood in for one, wrongly. Leaving the old name would
  // re-encode the thing W-0051 removed.
  it('withholds edit and delete from a linked account, not from a relationship', () => {
    const editButtons = wrapper.findAll('button').filter((button) => button.text() === 'Edit');
    const deleteButtons = wrapper.findAll('button').filter((button) => button.text() === 'Delete');

    expect(editButtons).toHaveLength(1);
    expect(deleteButtons).toHaveLength(1);
    expect(wrapper.text()).toContain('Account Linked');
    expect(wrapper.text()).toContain('Linked account');
  });

  it('gives an unlinked spouse the same edit and delete as any other record', async () => {
    const orphanWrapper = mountComponent(createTestStore([unlinkedSpouse]));
    await flushPromises();

    const editButtons = orphanWrapper.findAll('button').filter((button) => button.text() === 'Edit');
    const deleteButtons = orphanWrapper.findAll('button').filter((button) => button.text() === 'Delete');

    expect(editButtons).toHaveLength(1);
    expect(deleteButtons).toHaveLength(1);
  });

  it('does not claim a link an unlinked spouse does not have', async () => {
    const orphanWrapper = mountComponent(createTestStore([unlinkedSpouse]));
    await flushPromises();

    expect(orphanWrapper.text()).not.toContain('Account Linked');
    expect(orphanWrapper.text()).not.toContain('Linked account');
  });

  it('tells the user plainly that an unlinked spouse shares nothing yet', async () => {
    const orphanWrapper = mountComponent(createTestStore([unlinkedSpouse]));
    await flushPromises();

    expect(orphanWrapper.text()).toContain('not linked');
  });

  it('can open an unlinked spouse in edit mode', async () => {
    const orphanWrapper = mountComponent(createTestStore([unlinkedSpouse]));
    await flushPromises();

    const editButton = orphanWrapper.findAll('button').find((button) => button.text() === 'Edit');
    await editButton.trigger('click');

    expect(orphanWrapper.vm.selectedMember).toEqual(unlinkedSpouse);
  });

  it('opens the child in edit mode', async () => {
    const editButton = wrapper.findAll('button').find((button) => button.text() === 'Edit');
    await editButton.trigger('click');

    expect(wrapper.vm.selectedMember).toEqual(familyMembers[1]);
    expect(wrapper.findComponent({ name: 'FamilyMemberFormModal' }).props('member')).toEqual(familyMembers[1]);
  });

  it('marks dependent family members', () => {
    expect(wrapper.text()).toContain('Dependent');
  });

  it('formats dates in day-month-year order', () => {
    expect(wrapper.text()).toContain('15/05/1992');
    expect(wrapper.text()).toContain('20/08/2015');
  });

  it('uses existing profile family data without an unnecessary request', () => {
    expect(familyMembersService.getFamilyMembers).not.toHaveBeenCalled();
  });

  it('loads family members from the service when the profile store is empty', async () => {
    familyMembersService.getFamilyMembers.mockResolvedValue({
      data: { family_members: [familyMembers[1]] },
    });
    const emptyWrapper = mountComponent(createTestStore([]));
    await flushPromises();

    expect(familyMembersService.getFamilyMembers).toHaveBeenCalledTimes(1);
    expect(emptyWrapper.text()).toContain('Johnny Doe');
  });

  it('shows the empty state when no family members exist', async () => {
    const emptyWrapper = mountComponent(createTestStore([]));
    await flushPromises();

    expect(emptyWrapper.text()).toContain('No family members added yet');
    expect(emptyWrapper.text()).toContain('Add Your First Family Member');
  });

  it('creates a family member from the modal save event', async () => {
    const formData = {
      name: 'New Child',
      relationship: 'child',
      date_of_birth: '2020-01-01',
    };
    const addButton = wrapper.findAll('button').find((button) => button.text() === 'Add');
    await addButton.trigger('click');
    await wrapper.findComponent({ name: 'FamilyMemberFormModal' }).vm.$emit('save', formData);
    await flushPromises();

    expect(familyMembersService.createFamilyMember).toHaveBeenCalledWith(formData);
    expect(wrapper.vm.showModal).toBe(false);
  });

  /**
   * W-0472. CSJ's W-0349 decision stopped this endpoint creating an account for an
   * unregistered address — it invites it — and the address is **not stored**:
   * `family_members` has no email column and no invitation table exists.
   *
   * The component branched on `created` and `linked`. The invite path returns neither,
   * so it fell through to "Family member added successfully!" and the user was never
   * told an invitation had gone out at all. The `created` branch could not fire either:
   * the controller returns no such key and `created_new_user` is always false. **A
   * branch that cannot execute is worse than the limitation it hides.**
   *
   * Whether the address should be retained is CSJ's and compliance-lead's call
   * (acceptance 1). Saying an invitation went out is true either way.
   */
  it('says an invitation went out, and to which address', async () => {
    familyMembersService.createFamilyMember.mockResolvedValue({
      data: { family_member: { id: 9 }, linked: false, invitation_pending: true },
    });

    const addButton = wrapper.findAll('button').find((button) => button.text() === 'Add');
    await addButton.trigger('click');
    await wrapper.findComponent({ name: 'FamilyMemberFormModal' }).vm.$emit('save', {
      name: 'Sam Partner',
      relationship: 'spouse',
      email: 'sam@example.com',
    });
    await flushPromises();

    // The address the user just typed — disclosing nothing, since they typed it, and
    // the response deliberately confirms nothing about whether it is registered.
    expect(wrapper.vm.successMessage).toContain('sam@example.com');
    expect(wrapper.vm.successMessage).toContain('invitation');
    // And it says the address is not kept, because it is not.
    expect(wrapper.vm.successMessage).toContain('do not keep a record');
  });

  it('does not claim a family member was simply added when one was invited', async () => {
    familyMembersService.createFamilyMember.mockResolvedValue({
      data: { family_member: { id: 9 }, linked: false, invitation_pending: true },
    });

    const addButton = wrapper.findAll('button').find((button) => button.text() === 'Add');
    await addButton.trigger('click');
    await wrapper.findComponent({ name: 'FamilyMemberFormModal' }).vm.$emit('save', {
      name: 'Sam Partner',
      relationship: 'spouse',
      email: 'sam@example.com',
    });
    await flushPromises();

    expect(wrapper.vm.successMessage).not.toBe('Family member added successfully!');
  });

  it('updates an existing family member from the modal save event', async () => {
    const editButton = wrapper.findAll('button').find((button) => button.text() === 'Edit');
    await editButton.trigger('click');
    await wrapper.findComponent({ name: 'FamilyMemberFormModal' }).vm.$emit('save', {
      ...familyMembers[1],
      name: 'Johnny Smith',
    });
    await flushPromises();

    expect(familyMembersService.updateFamilyMember).toHaveBeenCalledWith(2, expect.objectContaining({
      name: 'Johnny Smith',
    }));
  });

  it('deletes the selected editable family member', async () => {
    wrapper.vm.confirmDelete(familyMembers[1]);
    await wrapper.vm.handleDelete();

    expect(familyMembersService.deleteFamilyMember).toHaveBeenCalledWith(2);
    expect(wrapper.vm.showDeleteConfirm).toBe(false);
  });
  /**
   * W-0132 — the card asked a question it already had the answer to.
   *
   * It read `users.charitable_bequest`, a column written by a toggle on /estate and
   * never loaded back into the client, so it answered "Not set" for a user whose
   * will contained a £10,000 charitable legacy the estate calculation was already
   * using to apply the reduced rate. NULL ("we have not asked you") and false ("you
   * told us no") were both falsy and rendered identically.
   *
   * The assertion that matters is the CROSS one: the auth user's toggle says false
   * throughout this suite, so a card that still consulted it would fail here even
   * though every figure on the page came from a fixture.
   *
   * **W-0221 dropped `users.charitable_bequest`**, so the server can no longer send
   * that key at all. The decoy below is KEPT deliberately: it is now a hostile value
   * that could never arrive, which is exactly what makes it a guard on the
   * COMPONENT. A card rewritten to read `auth.user.charitable_bequest` would get
   * `undefined`, fall to falsy, and print "No" on a will holding a legacy — the
   * original defect, reachable without the column.
   */
  describe('the charitable bequest card', () => {
    it('answers from the recorded will, not from the users.charitable_bequest toggle', async () => {
      // `auth.user.charitable_bequest` is false in this store, and the will has a
      // legacy in it. The old card printed "No".
      expect(store.state.auth.user.charitable_bequest).toBe(false);

      const text = wrapper.text();
      expect(text).toContain('Yes');
      expect(text).toContain('Your will records one charitable gift, totalling £10,000.');
    });

    it('no longer asks the user a question the will answers', () => {
      expect(wrapper.text()).not.toContain('Do you wish to leave anything to charity?');
    });

    it('never renders the unanswerable third state', async () => {
      // NULL and false were indistinguishable and produced "Not set", which the user
      // could not clear because the control had only two options.
      for (const summary of [oneFixedLegacy, { has_bequests: false, count: 0, fixed_total: 0, has_estate_share: false }, undefined]) {
        const scoped = mountComponent(createTestStore(familyMembers, summary));
        await flushPromises();

        expect(scoped.text()).not.toContain('Not set');
      }
    });

    it('says nothing is recorded when the will has no charitable gift', async () => {
      const scoped = mountComponent(createTestStore(familyMembers, {
        has_bequests: false,
        count: 0,
        fixed_total: 0,
        has_estate_share: false,
      }));
      await flushPromises();

      expect(scoped.text()).toContain('None recorded');
      expect(scoped.text()).toContain('Your will records no gifts to charity.');
    });

    it('names a share of the estate rather than totalling it as nothing', async () => {
      // A percentage or residuary gift has no value until an estate is valued. It
      // must be named, never counted as £0 inside a printed total.
      const scoped = mountComponent(createTestStore(familyMembers, {
        has_bequests: true,
        count: 1,
        fixed_total: 0,
        has_estate_share: true,
      }));
      await flushPromises();

      expect(scoped.text()).toContain('Your will records one charitable gift, given as a share of your estate.');
      expect(scoped.text()).not.toContain('totalling £0');
    });

    it('states both parts when the will mixes a fixed sum with a share of the estate', async () => {
      const scoped = mountComponent(createTestStore(familyMembers, {
        has_bequests: true,
        count: 2,
        fixed_total: 10000,
        has_estate_share: true,
      }));
      await flushPromises();

      expect(scoped.text()).toContain('Your will records 2 charitable gifts: £10,000 in fixed sums, plus a share of your estate.');
    });
  });
});
