/**
 * Mock data for Cash Overview transaction-based features.
 * This provides placeholder data for future transaction integration.
 */

// Spending categories for the donut chart (last 30 days)
export const MOCK_SPENDING_CATEGORIES = {
  'Groceries': 485.50,
  'Dining Out': 267.30,
  'Transport': 142.80,
  'Shopping': 198.45,
  'Bills & Utilities': 425.00,
  'Entertainment': 89.99,
  'Other': 156.20,
};

// Generate mock balance trend data for the line chart
export function generateMockBalanceTrend(baseBalance = 4500, days = 30) {
  const data = [];
  let balance = baseBalance;
  const now = new Date();

  for (let i = days; i >= 0; i--) {
    const date = new Date(now);
    date.setDate(date.getDate() - i);

    // Simulate realistic balance fluctuations
    // Larger drops around typical bill dates (1st, 15th)
    const dayOfMonth = date.getDate();
    if (dayOfMonth === 1 || dayOfMonth === 15) {
      balance -= Math.random() * 800 + 200; // Bill payments
    } else if (dayOfMonth >= 25 && dayOfMonth <= 28) {
      balance += Math.random() * 2000 + 1500; // Salary credit
    } else {
      balance += (Math.random() - 0.6) * 150; // Daily fluctuation (slightly negative bias)
    }

    // Keep balance reasonable
    balance = Math.max(500, Math.min(balance, 8000));

    data.push({
      date: date.toISOString().split('T')[0],
      value: Math.round(balance * 100) / 100,
    });
  }

  return data;
}

// Mock payday data
export const MOCK_PAYDAY = {
  daysUntil: 3,
  expectedDate: (() => {
    const date = new Date();
    date.setDate(date.getDate() + 3);
    return date.toISOString().split('T')[0];
  })(),
  expectedAmount: 3850.00,
};

// Mock clear cash calculation
export const MOCK_CLEAR_CASH = {
  predictedAvailable: 2975.00,
  pendingTransactions: 125.00,
  upcomingBills: 450.00,
};

// Mock money in/out for current month (current accounts)
export const MOCK_MONEY_FLOW = {
  income: {
    amount: 4250.00,
    forecast: 4250.00,
    label: 'Money In',
  },
  expenses: {
    amount: 2847.50,
    forecast: 3200.00,
    label: 'Money Out',
  },
};

// Mock money in/out for savings accounts
export const MOCK_SAVINGS_FLOW = {
  income: {
    amount: 500.00,
    label: 'Deposits',
  },
  expenses: {
    amount: 0.00,
    label: 'Withdrawals',
  },
};

// Mock credit card spending this month
export const MOCK_CREDIT_CARD_SPENDING = {
  amount: 892.45,
  month: new Date().toLocaleString('default', { month: 'long' }),
};

// Mock alerts
export const MOCK_ALERTS = [
  {
    id: 1,
    type: 'warning',
    message: 'Low balance alert: Current account below £500',
    action: 'View account',
  },
  {
    id: 2,
    type: 'info',
    message: 'ISA allowance reminder: £8,500 remaining',
    action: 'Learn more',
  },
];

// Static tips
export const CASH_TIPS = [
  'Keep 3-6 months of expenses in your emergency fund.',
  'Review your direct debits quarterly to spot unused subscriptions.',
  'Consider a notice account for higher interest on savings you don\'t need immediately.',
  'Maximise your ISA allowance before the tax year ends (5 April).',
];

// Get a random tip
export function getRandomTip() {
  return CASH_TIPS[Math.floor(Math.random() * CASH_TIPS.length)];
}
