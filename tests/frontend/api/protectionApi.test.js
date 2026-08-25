import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}));

import api from '@/services/api';
import protectionService from '@/services/protectionService';

describe('protection service API contract', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('fetches the authenticated protection summary', async () => {
    const payload = { success: true, data: { policies: [] } };
    api.get.mockResolvedValue({ data: payload });

    await expect(protectionService.getProtectionData()).resolves.toEqual(payload);
    expect(api.get).toHaveBeenCalledWith('/protection');
  });

  it('saves the protection profile', async () => {
    const profile = { annual_income: 50000, number_of_dependents: 2 };
    api.post.mockResolvedValue({ data: { success: true, data: profile } });

    await expect(protectionService.saveProfile(profile)).resolves.toMatchObject({ success: true });
    expect(api.post).toHaveBeenCalledWith('/protection/profile', profile);
  });

  it('updates the no-policies declaration', async () => {
    api.patch.mockResolvedValue({ data: { success: true } });

    await protectionService.updateHasNoPolicies(true);

    expect(api.patch).toHaveBeenCalledWith('/protection/profile/has-no-policies', {
      has_no_policies: true,
    });
  });

  it('creates a life insurance policy with the supplied fields', async () => {
    const policy = { provider: 'Test Insurance', sum_assured: 500000 };
    api.post.mockResolvedValue({ data: { success: true, data: policy } });

    await expect(protectionService.createLifePolicy(policy)).resolves.toMatchObject({ success: true });
    expect(api.post).toHaveBeenCalledWith('/protection/policies/life', policy);
  });

  it('preserves validation failures from life insurance creation', async () => {
    const validationError = Object.assign(new Error('Validation failed'), {
      response: { status: 422, data: { errors: { sum_assured: ['Required'] } } },
    });
    api.post.mockRejectedValue(validationError);

    await expect(protectionService.createLifePolicy({ provider: 'Test Insurance' }))
      .rejects.toBe(validationError);
  });

  it('creates a critical illness policy', async () => {
    const policy = { provider: 'Critical Care', sum_assured: 100000 };
    api.post.mockResolvedValue({ data: { success: true, data: policy } });

    await protectionService.createCriticalIllnessPolicy(policy);

    expect(api.post).toHaveBeenCalledWith('/protection/policies/critical-illness', policy);
  });

  it('creates an income protection policy', async () => {
    const policy = { provider: 'Income Protect', benefit_amount: 3000 };
    api.post.mockResolvedValue({ data: { success: true, data: policy } });

    await protectionService.createIncomeProtectionPolicy(policy);

    expect(api.post).toHaveBeenCalledWith('/protection/policies/income-protection', policy);
  });

  it('requests protection analysis with the supplied assumptions', async () => {
    const assumptions = { income_growth_rate: 2 };
    api.post.mockResolvedValue({ data: { success: true, data: { coverage_gaps: [] } } });

    await protectionService.analyzeProtection(assumptions);

    expect(api.post).toHaveBeenCalledWith('/protection/analyze', assumptions);
  });

  it('fetches protection recommendations', async () => {
    api.get.mockResolvedValue({ data: { success: true, data: [] } });

    await protectionService.getRecommendations();

    expect(api.get).toHaveBeenCalledWith('/protection/recommendations');
  });

  it('runs a what-if scenario', async () => {
    const scenario = { scenario_type: 'death', coverage_adjustment: 100000 };
    api.post.mockResolvedValue({ data: { success: true, data: scenario } });

    await protectionService.runScenario(scenario);

    expect(api.post).toHaveBeenCalledWith('/protection/scenarios', scenario);
  });
});
