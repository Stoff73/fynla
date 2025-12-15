import userProfileService from '@/services/userProfileService';

// Helper to get user-specific localStorage key
const getUserStorageKey = (key) => {
  const user = JSON.parse(localStorage.getItem('user') || '{}');
  return user.id ? `${key}_user_${user.id}` : key;
};

// Restore personalAccounts from localStorage if available
const getInitialPersonalAccounts = () => {
  try {
    const key = getUserStorageKey('personalAccounts_data');
    const savedData = localStorage.getItem(key);
    if (savedData) {
      return JSON.parse(savedData);
    }
  } catch (error) {
    console.error('Failed to restore personalAccounts from localStorage:', error);
  }
  return {
    profitAndLoss: null,
    cashflow: null,
    balanceSheet: null,
  };
};

// Restore spouse personalAccounts from localStorage if available
const getInitialSpouseAccounts = () => {
  try {
    const key = getUserStorageKey('spouseAccounts_data');
    const savedData = localStorage.getItem(key);
    if (savedData) {
      return JSON.parse(savedData);
    }
  } catch (error) {
    console.error('Failed to restore spouseAccounts from localStorage:', error);
  }
  return null;
};

const state = {
  profile: null,
  personalInfo: null,
  familyMembers: [],
  incomeOccupation: null,
  personalAccounts: getInitialPersonalAccounts(),
  spouseAccounts: getInitialSpouseAccounts(),
  loading: false,
  error: null,
};

const getters = {
  profile: (state) => state.profile,
  user: (state, getters, rootState, rootGetters) => {
    // Get user from auth store which has the most up-to-date user object
    return rootGetters['auth/user'];
  },
  domicileInfo: (state) => {
    // Return domicile info from profile data
    return state.profile?.domicile_info || null;
  },
  personalInfo: (state) => state.personalInfo,
  familyMembers: (state) => state.familyMembers,
  spouse: (state, getters, rootState, rootGetters) => {
    // Get current user from auth store
    const currentUser = rootGetters['auth/user'];

    if (!currentUser || !currentUser.spouse_id) {
      return null;
    }

    // Try to find spouse in family members first (has more details like date_of_birth)
    const spouseInFamily = state.familyMembers.find(member => member.relationship === 'spouse');
    if (spouseInFamily) {
      // Add the spouse_id to the object for consistency
      return {
        ...spouseInFamily,
        id: currentUser.spouse_id,
      };
    }

    // If not in family members, check if spouse relationship is loaded from user
    if (currentUser.spouse) {
      return {
        id: currentUser.spouse_id,
        name: currentUser.spouse.name || 'Spouse',
        email: currentUser.spouse.email,
        relationship: 'spouse',
      };
    }

    // Fallback: return basic spouse info with just the ID
    return {
      id: currentUser.spouse_id,
      name: 'Spouse', // Placeholder name
      relationship: 'spouse',
    };
  },
  incomeOccupation: (state) => state.incomeOccupation,
  totalAnnualIncome: (state) => {
    if (!state.incomeOccupation) return 0;
    return state.incomeOccupation.total_annual_income || 0;
  },
  personalAccounts: (state) => state.personalAccounts,
  spouseAccounts: (state) => state.spouseAccounts,
  loading: (state) => state.loading,
  error: (state) => state.error,
};

const actions = {
  /**
   * Fetch complete user profile
   */
  async fetchProfile({ commit }) {
    commit('setLoading', true);
    commit('setError', null);

    try {
      const response = await userProfileService.getProfile();

      if (response.success) {
        commit('setProfile', response.data);
        commit('setPersonalInfo', response.data.personal_info);
        commit('setIncomeOccupation', response.data.income_occupation);
        commit('setFamilyMembers', response.data.family_members || []);
      }

      return response;
    } catch (error) {
      const errorMessage = error.response?.data?.message || error.message || 'Failed to fetch profile';
      commit('setError', errorMessage);
      throw error;
    } finally {
      commit('setLoading', false);
    }
  },

  /**
   * Update personal information
   */
  async updatePersonalInfo({ commit }, data) {
    commit('setLoading', true);
    commit('setError', null);

    try {
      const response = await userProfileService.updatePersonalInfo(data);

      if (response.success) {
        // Transform user data to match the expected format
        const user = response.data.user;

        // Format date_of_birth to yyyy-MM-dd for HTML date input
        let formattedDateOfBirth = null;
        if (user.date_of_birth) {
          const date = new Date(user.date_of_birth);
          formattedDateOfBirth = date.toISOString().split('T')[0];
        }

        const personalInfo = {
          id: user.id,
          name: user.name,
          email: user.email,
          date_of_birth: formattedDateOfBirth,
          gender: user.gender,
          marital_status: user.marital_status,
          national_insurance_number: user.national_insurance_number,
          phone: user.phone,
          education_level: user.education_level,
          good_health: user.good_health,
          smoker: user.smoker,
          address: {
            line_1: user.address_line_1,
            line_2: user.address_line_2,
            city: user.city,
            county: user.county,
            postcode: user.postcode,
          },
        };

        commit('setPersonalInfo', personalInfo);

        // Update auth store user as well
        commit('auth/setUser', user, { root: true });
      }

      return response;
    } catch (error) {
      const errorMessage = error.response?.data?.message || error.message || 'Failed to update personal information';
      commit('setError', errorMessage);
      throw error;
    } finally {
      commit('setLoading', false);
    }
  },

  /**
   * Update income and occupation information
   */
  async updateIncomeOccupation({ commit }, data) {
    commit('setLoading', true);
    commit('setError', null);

    try {
      const response = await userProfileService.updateIncomeOccupation(data);

      if (response.success) {
        const user = response.data.user;

        // Transform user data to match the expected format
        const incomeOccupation = {
          occupation: user.occupation,
          employer: user.employer,
          industry: user.industry,
          employment_status: user.employment_status,
          target_retirement_age: user.target_retirement_age,
          retirement_date: user.retirement_date,
          annual_employment_income: user.annual_employment_income,
          annual_self_employment_income: user.annual_self_employment_income,
          annual_rental_income: user.annual_rental_income,
          annual_dividend_income: user.annual_dividend_income,
          annual_other_income: user.annual_other_income,
          total_annual_income: (
            (user.annual_employment_income || 0) +
            (user.annual_self_employment_income || 0) +
            (user.annual_rental_income || 0) +
            (user.annual_dividend_income || 0) +
            (user.annual_other_income || 0)
          ),
        };

        commit('setIncomeOccupation', incomeOccupation);

        // Update auth store user as well
        commit('auth/setUser', user, { root: true });
      }

      return response;
    } catch (error) {
      const errorMessage = error.response?.data?.message || error.message || 'Failed to update income/occupation';
      commit('setError', errorMessage);
      throw error;
    } finally {
      commit('setLoading', false);
    }
  },

  /**
   * Update expenditure information
   */
  async updateExpenditure({ commit }, data) {
    commit('setLoading', true);
    commit('setError', null);

    try {
      const response = await userProfileService.updateExpenditure(data);

      if (response.success) {
        // Refresh the full profile to get updated expenditure
        const profileResponse = await userProfileService.getProfile();
        if (profileResponse.success) {
          commit('setProfile', profileResponse.data.profile);
        }
      }

      return response;
    } catch (error) {
      const errorMessage = error.response?.data?.message || error.message || 'Failed to update expenditure';
      commit('setError', errorMessage);
      throw error;
    } finally {
      commit('setLoading', false);
    }
  },

  /**
   * Update spouse expenditure information
   */
  async updateSpouseExpenditure({ commit }, { spouseId, expenditureData }) {
    commit('setLoading', true);
    commit('setError', null);

    try {
      const response = await userProfileService.updateSpouseExpenditure(spouseId, expenditureData);

      if (response.success) {
        // Refresh the full profile to get updated expenditure
        const profileResponse = await userProfileService.getProfile();
        if (profileResponse.success) {
          commit('setProfile', profileResponse.data.profile);
        }
      }

      return response;
    } catch (error) {
      const errorMessage = error.response?.data?.message || error.message || 'Failed to update spouse expenditure';
      commit('setError', errorMessage);
      throw error;
    } finally {
      commit('setLoading', false);
    }
  },

  /**
   * Fetch family members
   */
  async fetchFamilyMembers({ commit }) {
    commit('setLoading', true);
    commit('setError', null);

    try {
      const response = await userProfileService.getFamilyMembers();

      if (response.success) {
        commit('setFamilyMembers', response.data.family_members || []);
      }

      return response;
    } catch (error) {
      const errorMessage = error.response?.data?.message || error.message || 'Failed to fetch family members';
      commit('setError', errorMessage);
      throw error;
    } finally {
      commit('setLoading', false);
    }
  },

  /**
   * Add a new family member
   */
  async addFamilyMember({ commit }, data) {
    commit('setLoading', true);
    commit('setError', null);

    try {
      const response = await userProfileService.createFamilyMember(data);

      if (response.success) {
        commit('addFamilyMember', response.data.family_member);
      }

      return response;
    } catch (error) {
      const errorMessage = error.response?.data?.message || error.message || 'Failed to add family member';
      commit('setError', errorMessage);
      throw error;
    } finally {
      commit('setLoading', false);
    }
  },

  /**
   * Update a family member
   */
  async updateFamilyMember({ commit }, { id, data }) {
    commit('setLoading', true);
    commit('setError', null);

    try {
      const response = await userProfileService.updateFamilyMember(id, data);

      if (response.success) {
        commit('updateFamilyMember', response.data.family_member);
      }

      return response;
    } catch (error) {
      const errorMessage = error.response?.data?.message || error.message || 'Failed to update family member';
      commit('setError', errorMessage);
      throw error;
    } finally {
      commit('setLoading', false);
    }
  },

  /**
   * Delete a family member
   */
  async deleteFamilyMember({ commit }, id) {
    commit('setLoading', true);
    commit('setError', null);

    try {
      const response = await userProfileService.deleteFamilyMember(id);

      if (response.success) {
        commit('removeFamilyMember', id);
      }

      return response;
    } catch (error) {
      const errorMessage = error.response?.data?.message || error.message || 'Failed to delete family member';
      commit('setError', errorMessage);
      throw error;
    } finally {
      commit('setLoading', false);
    }
  },

  /**
   * Calculate personal accounts
   */
  async calculatePersonalAccounts({ commit }, params = {}) {
    commit('setLoading', true);
    commit('setError', null);

    try {
      const response = await userProfileService.calculatePersonalAccounts(params);

      if (response.success) {
        commit('setPersonalAccounts', response.data);
      }

      return response;
    } catch (error) {
      const errorMessage = error.response?.data?.message || error.message || 'Failed to calculate personal accounts';
      commit('setError', errorMessage);
      throw error;
    } finally {
      commit('setLoading', false);
    }
  },

  /**
   * Add a line item to personal accounts
   */
  async addLineItem({ commit, dispatch }, data) {
    commit('setLoading', true);
    commit('setError', null);

    try {
      const response = await userProfileService.createLineItem(data);

      if (response.success) {
        // Recalculate accounts after adding line item
        await dispatch('calculatePersonalAccounts');
      }

      return response;
    } catch (error) {
      const errorMessage = error.response?.data?.message || error.message || 'Failed to add line item';
      commit('setError', errorMessage);
      throw error;
    } finally {
      commit('setLoading', false);
    }
  },

  /**
   * Update a line item
   */
  async updateLineItem({ commit, dispatch }, { id, data }) {
    commit('setLoading', true);
    commit('setError', null);

    try {
      const response = await userProfileService.updateLineItem(id, data);

      if (response.success) {
        // Recalculate accounts after updating line item
        await dispatch('calculatePersonalAccounts');
      }

      return response;
    } catch (error) {
      const errorMessage = error.response?.data?.message || error.message || 'Failed to update line item';
      commit('setError', errorMessage);
      throw error;
    } finally {
      commit('setLoading', false);
    }
  },

  /**
   * Delete a line item
   */
  async deleteLineItem({ commit, dispatch }, id) {
    commit('setLoading', true);
    commit('setError', null);

    try {
      const response = await userProfileService.deleteLineItem(id);

      if (response.success) {
        // Recalculate accounts after deleting line item
        await dispatch('calculatePersonalAccounts');
      }

      return response;
    } catch (error) {
      const errorMessage = error.response?.data?.message || error.message || 'Failed to delete line item';
      commit('setError', errorMessage);
      throw error;
    } finally {
      commit('setLoading', false);
    }
  },
};

const mutations = {
  setProfile(state, profile) {
    state.profile = profile;
  },

  setPersonalInfo(state, personalInfo) {
    state.personalInfo = personalInfo;
  },

  setFamilyMembers(state, familyMembers) {
    state.familyMembers = familyMembers;
  },

  setIncomeOccupation(state, incomeOccupation) {
    state.incomeOccupation = incomeOccupation;
  },

  setPersonalAccounts(state, data) {
    state.personalAccounts = {
      profitAndLoss: data.profit_and_loss || null,
      cashflow: data.cashflow || null,
      balanceSheet: data.balance_sheet || null,
    };
    // Persist to localStorage with user-specific key
    const userKey = getUserStorageKey('personalAccounts_data');
    localStorage.setItem(userKey, JSON.stringify(state.personalAccounts));

    // Handle spouse data if available
    if (data.spouse_data) {
      state.spouseAccounts = {
        profitAndLoss: data.spouse_data.profit_and_loss || null,
        cashflow: data.spouse_data.cashflow || null,
        balanceSheet: data.spouse_data.balance_sheet || null,
      };
      const spouseKey = getUserStorageKey('spouseAccounts_data');
      localStorage.setItem(spouseKey, JSON.stringify(state.spouseAccounts));
    } else {
      state.spouseAccounts = null;
      const spouseKey = getUserStorageKey('spouseAccounts_data');
      localStorage.removeItem(spouseKey);
    }
  },

  addFamilyMember(state, familyMember) {
    state.familyMembers.push(familyMember);
  },

  updateFamilyMember(state, updatedMember) {
    const index = state.familyMembers.findIndex(m => m.id === updatedMember.id);
    if (index !== -1) {
      state.familyMembers.splice(index, 1, updatedMember);
    }
  },

  removeFamilyMember(state, id) {
    state.familyMembers = state.familyMembers.filter(m => m.id !== id);
  },

  setLoading(state, loading) {
    state.loading = loading;
  },

  setError(state, error) {
    state.error = error;
  },
};

export default {
  namespaced: true,
  state,
  getters,
  actions,
  mutations,
};
