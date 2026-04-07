/**
 * Learn Hub - Static topic data
 *
 * Each topic includes Fyn intro text, key info items,
 * and links to authoritative external guides (MoneyHelper, HMRC, Pension Wise).
 */
import { STATE_PENSION_WEEKLY, TAX_YEAR, PENSION_ANNUAL_ALLOWANCE } from '@/constants/taxConfig';

const learnTopics = [
    {
        id: 'tax',
        label: 'Tax',
        icon: '\uD83D\uDCB7',
        fynIntro: 'Understanding your tax position helps you keep more of what you earn. Here are the key things to know about UK tax.',
        keyInfo: [
            {
                title: 'Personal Allowance',
                summary: 'Most people can earn up to \u00A312,570 tax-free each year.',
                detail: 'The Personal Allowance is the amount of income you don\'t pay tax on. It reduces by \u00A31 for every \u00A32 earned above \u00A3100,000, meaning it\'s fully withdrawn at \u00A3125,140.',
            },
            {
                title: 'Income Tax Bands',
                summary: 'Tax rates range from 20% basic rate up to 45% additional rate.',
                detail: 'Basic rate (20%) applies to income from \u00A312,571 to \u00A350,270. Higher rate (40%) from \u00A350,271 to \u00A3125,140. Additional rate (45%) above \u00A3125,140. Scottish taxpayers have different bands.',
            },
            {
                title: 'National Insurance',
                summary: 'National Insurance contributions fund your State Pension and certain benefits.',
                detail: 'Employees pay Class 1 NICs at 8% on earnings between \u00A312,570 and \u00A350,270, then 2% above that. Self-employed pay Class 4 at similar rates plus a flat Class 2 contribution.',
            },
        ],
        guides: [
            { title: 'Income Tax rates and Personal Allowances', source: 'HMRC', url: 'https://www.gov.uk/income-tax-rates' },
            { title: 'Tax on your private pension contributions', source: 'HMRC', url: 'https://www.gov.uk/tax-on-your-private-pension/pension-tax-relief' },
            { title: 'MoneyHelper tax guide', source: 'MoneyHelper', url: 'https://www.moneyhelper.org.uk/en/money-troubles/tax' },
        ],
        fynPrompt: 'Can you review my tax position and suggest ways to be more tax-efficient?',
    },
    {
        id: 'pensions',
        label: 'Pensions',
        icon: '\uD83C\uDFE6',
        fynIntro: 'Your pension is likely your biggest financial asset after your home. Understanding how it works can make a big difference to your retirement.',
        keyInfo: [
            {
                title: 'Annual Allowance',
                summary: `You can contribute up to \u00A3${PENSION_ANNUAL_ALLOWANCE.toLocaleString()} per year with tax relief.`,
                detail: 'The Annual Allowance is the most you can save into pensions each tax year while still getting tax relief. Unused allowance can be carried forward from the previous 3 years. High earners may have a reduced allowance (tapered).',
            },
            {
                title: 'Lifetime Allowance',
                summary: 'The Lifetime Allowance charge was removed from April 2024.',
                detail: 'While the Lifetime Allowance itself has been abolished, there are new limits on tax-free lump sums. The Lump Sum Allowance is \u00A3268,275 and the Lump Sum and Death Benefit Allowance is \u00A31,073,100.',
            },
            {
                title: 'State Pension',
                summary: `The full new State Pension is \u00A3${STATE_PENSION_WEEKLY.toFixed(2)} per week (${TAX_YEAR}).`,
                detail: 'You need 35 qualifying years of National Insurance contributions for the full amount. You can check your State Pension forecast on the GOV.UK website. State Pension age is currently 66, rising to 67 by 2028.',
            },
        ],
        guides: [
            { title: 'Check your State Pension forecast', source: 'GOV.UK', url: 'https://www.gov.uk/check-state-pension' },
            { title: 'Pension Wise - free guidance', source: 'Pension Wise', url: 'https://www.moneyhelper.org.uk/en/pensions-and-retirement/pension-wise' },
            { title: 'Understanding your workplace pension', source: 'MoneyHelper', url: 'https://www.moneyhelper.org.uk/en/pensions-and-retirement/pensions-basics/workplace-pensions' },
        ],
        fynPrompt: 'Can you review my pension arrangements and tell me if I\'m on track for retirement?',
    },
    {
        id: 'protection',
        label: 'Protection',
        icon: '\uD83D\uDEE1\uFE0F',
        fynIntro: 'Protection insurance covers your family if something unexpected happens. It\'s about making sure your loved ones are financially secure.',
        keyInfo: [
            {
                title: 'Life Insurance',
                summary: 'Pays a lump sum or regular income if you die during the policy term.',
                detail: 'Term life insurance runs for a set period and is the most affordable type. Level term pays a fixed sum, while decreasing term reduces over time (often used alongside a mortgage). Whole-of-life covers you for your entire life.',
            },
            {
                title: 'Income Protection',
                summary: 'Replaces part of your income if you can\'t work due to illness or injury.',
                detail: 'Typically pays up to 60% of your pre-tax income. Policies differ on deferral periods (how long before payments start) and definitions of incapacity. Short-term policies last 1-2 years; long-term can pay until retirement.',
            },
            {
                title: 'Critical Illness Cover',
                summary: 'Pays a tax-free lump sum if you\'re diagnosed with a specified critical illness.',
                detail: 'Covers conditions like cancer, heart attack, and stroke. Policies vary on which conditions are covered and how they\'re defined. Can be standalone or added to a life insurance policy.',
            },
        ],
        guides: [
            { title: 'Do you need life insurance?', source: 'MoneyHelper', url: 'https://www.moneyhelper.org.uk/en/everyday-money/insurance/do-you-need-life-insurance' },
            { title: 'Income protection insurance', source: 'MoneyHelper', url: 'https://www.moneyhelper.org.uk/en/everyday-money/insurance/income-protection-insurance' },
            { title: 'Critical illness insurance explained', source: 'MoneyHelper', url: 'https://www.moneyhelper.org.uk/en/everyday-money/insurance/critical-illness-insurance' },
        ],
        fynPrompt: 'Can you review my protection policies and check if my family is adequately covered?',
    },
    {
        id: 'investing',
        label: 'Investing',
        icon: '\uD83D\uDCC8',
        fynIntro: 'Investing can help your money grow over the long term. Understanding the basics helps you make informed decisions.',
        keyInfo: [
            {
                title: 'Risk and Return',
                summary: 'Higher potential returns generally come with higher risk.',
                detail: 'Diversifying across different asset classes (shares, bonds, property, cash) helps manage risk. Your investment time horizon matters: longer periods can ride out short-term volatility. Your attitude to risk should match your circumstances.',
            },
            {
                title: 'Tax-Efficient Investing',
                summary: 'ISAs and pensions are the main tax-efficient wrappers in the UK.',
                detail: 'ISAs shelter investments from income tax and capital gains tax. You can invest up to \u00A320,000 per tax year across all ISA types. Pensions offer tax relief on contributions. Using your allowances effectively can save significant tax over time.',
            },
            {
                title: 'Investment Costs',
                summary: 'Fees and charges can significantly reduce your returns over time.',
                detail: 'Common charges include platform fees, fund management charges (OCF/TER), and dealing fees. Even small differences in fees compound over decades. Index (tracker) funds typically have lower charges than actively managed funds.',
            },
        ],
        guides: [
            { title: 'Investing for beginners', source: 'MoneyHelper', url: 'https://www.moneyhelper.org.uk/en/savings/investing/investing-beginners' },
            { title: 'Stocks and Shares ISA guide', source: 'MoneyHelper', url: 'https://www.moneyhelper.org.uk/en/savings/types-of-savings/stocks-and-shares-isas' },
            { title: 'Capital Gains Tax', source: 'HMRC', url: 'https://www.gov.uk/capital-gains-tax' },
        ],
        fynPrompt: 'Can you review my investment portfolio and suggest improvements?',
    },
    {
        id: 'estate',
        label: 'Estate Planning',
        icon: '\uD83C\uDFE0',
        fynIntro: 'Estate planning ensures your assets go where you want and can help reduce Inheritance Tax. It\'s not just for the wealthy.',
        keyInfo: [
            {
                title: 'Inheritance Tax',
                summary: 'Inheritance Tax is charged at 40% on estates above the Nil-Rate Band.',
                detail: 'Everyone has a Nil-Rate Band of \u00A3325,000. If you leave your home to direct descendants, you may also get the Residence Nil-Rate Band of \u00A3175,000. Married couples can transfer unused allowances to the surviving spouse.',
            },
            {
                title: 'Wills',
                summary: 'Having a valid will ensures your wishes are carried out.',
                detail: 'Without a will (dying intestate), your estate is distributed according to fixed rules that may not match your wishes. A will lets you name guardians for children, specify beneficiaries, and appoint executors. Review your will regularly, especially after life changes.',
            },
            {
                title: 'Trusts',
                summary: 'Trusts can protect assets and potentially reduce tax.',
                detail: 'Common types include discretionary trusts, bare trusts, and interest-in-possession trusts. Trusts can protect assets for vulnerable beneficiaries, help with Inheritance Tax planning, and provide flexibility. Professional advice is recommended.',
            },
        ],
        guides: [
            { title: 'Inheritance Tax guide', source: 'HMRC', url: 'https://www.gov.uk/inheritance-tax' },
            { title: 'Making a will', source: 'MoneyHelper', url: 'https://www.moneyhelper.org.uk/en/family-and-care/death-and-bereavement/making-a-will' },
            { title: 'Trusts explained', source: 'MoneyHelper', url: 'https://www.moneyhelper.org.uk/en/family-and-care/death-and-bereavement/trusts-and-tax' },
        ],
        fynPrompt: 'Can you review my estate plan and check my Inheritance Tax exposure?',
    },
    {
        id: 'budgeting',
        label: 'Budgeting',
        icon: '\uD83D\uDCB0',
        fynIntro: 'Good budgeting is the foundation of financial planning. Knowing where your money goes helps you save more and spend wisely.',
        keyInfo: [
            {
                title: 'Emergency Fund',
                summary: 'Aim to save 3-6 months of essential expenses in an easy-access account.',
                detail: 'An emergency fund protects you from unexpected costs like car repairs, boiler breakdowns, or job loss. Keep it in a separate easy-access savings account. Build it gradually if saving the full amount feels daunting.',
            },
            {
                title: 'The 50/30/20 Rule',
                summary: 'A simple guideline: 50% needs, 30% wants, 20% savings and debt repayment.',
                detail: 'Needs include housing, utilities, food, and transport. Wants cover dining out, entertainment, and subscriptions. Savings and debt repayment is your path to financial goals. Adjust the percentages to fit your circumstances.',
            },
            {
                title: 'Reducing Expenditure',
                summary: 'Small regular savings add up to large amounts over time.',
                detail: 'Review subscriptions and cancel unused ones. Compare energy, broadband, and insurance providers annually. Consider switching to cheaper alternatives for regular purchases. Meal planning can significantly reduce food waste and costs.',
            },
        ],
        guides: [
            { title: 'Budget planner', source: 'MoneyHelper', url: 'https://www.moneyhelper.org.uk/en/everyday-money/budgeting/budget-planner' },
            { title: 'How to save money', source: 'MoneyHelper', url: 'https://www.moneyhelper.org.uk/en/everyday-money/budgeting/beginners-guide-to-managing-your-money' },
            { title: 'Benefits calculator', source: 'GOV.UK', url: 'https://www.gov.uk/benefits-calculators' },
        ],
        fynPrompt: 'Can you help me review my spending and suggest areas where I could save?',
    },
    {
        id: 'isas',
        label: 'ISAs',
        icon: '\uD83D\uDCE6',
        fynIntro: 'Individual Savings Accounts (ISAs) let you save and invest tax-free. Using your annual allowance is one of the simplest tax planning steps.',
        keyInfo: [
            {
                title: 'ISA Allowance',
                summary: 'You can put up to \u00A320,000 into ISAs each tax year.',
                detail: 'The \u00A320,000 allowance can be split across Cash ISAs, Stocks and Shares ISAs, Innovative Finance ISAs, and Lifetime ISAs. You can only open one of each type per tax year. Any unused allowance cannot be carried forward.',
            },
            {
                title: 'ISA Types',
                summary: 'There are several ISA types to suit different savings goals.',
                detail: 'Cash ISAs earn interest tax-free. Stocks and Shares ISAs hold investments tax-free. Lifetime ISAs give a 25% government bonus (up to \u00A31,000/year) for first homes or retirement. Junior ISAs allow parents to save up to \u00A39,000/year for children.',
            },
            {
                title: 'Flexible ISAs',
                summary: 'Some ISAs let you withdraw and replace money without losing your allowance.',
                detail: 'With a flexible ISA, if you withdraw money and replace it within the same tax year, it doesn\'t count against your annual allowance. Not all providers offer flexible ISAs, so check before opening an account.',
            },
        ],
        guides: [
            { title: 'ISA guide', source: 'MoneyHelper', url: 'https://www.moneyhelper.org.uk/en/savings/types-of-savings/individual-savings-accounts-isas' },
            { title: 'Lifetime ISA', source: 'HMRC', url: 'https://www.gov.uk/lifetime-isa' },
            { title: 'Junior ISA', source: 'HMRC', url: 'https://www.gov.uk/junior-individual-savings-accounts' },
        ],
        fynPrompt: 'Can you check how I\'m using my ISA allowance and suggest improvements?',
    },
    {
        id: 'goals',
        label: 'Goal Planning',
        icon: '\uD83C\uDF1F',
        fynIntro: 'Setting clear financial goals gives your money purpose and helps you stay motivated. Let\'s make your goals achievable.',
        keyInfo: [
            {
                title: 'Setting Goals',
                summary: 'Effective financial goals are specific, measurable, and time-bound.',
                detail: 'Rather than "save more money", try "save \u00A310,000 for a house deposit by December 2026". Break large goals into smaller milestones. Prioritise goals by urgency and importance. Review and adjust regularly.',
            },
            {
                title: 'Short vs Long Term',
                summary: 'Different time horizons suit different savings and investment approaches.',
                detail: 'Short-term goals (under 5 years): use cash savings for stability. Medium-term (5-10 years): consider a mix of cash and investments. Long-term (10+ years): investments typically offer better growth potential despite short-term volatility.',
            },
            {
                title: 'Tracking Progress',
                summary: 'Regular tracking keeps you motivated and helps spot problems early.',
                detail: 'Set up automatic contributions where possible. Review progress monthly or quarterly. Celebrate milestones to stay motivated. If you fall behind, adjust your plan rather than giving up. Fynla tracks your goals automatically.',
            },
        ],
        guides: [
            { title: 'Setting financial goals', source: 'MoneyHelper', url: 'https://www.moneyhelper.org.uk/en/everyday-money/budgeting/how-to-set-financial-goals' },
            { title: 'Saving for a mortgage deposit', source: 'MoneyHelper', url: 'https://www.moneyhelper.org.uk/en/homes/buying-a-home/saving-for-a-mortgage-deposit' },
            { title: 'Planning for retirement', source: 'Pension Wise', url: 'https://www.moneyhelper.org.uk/en/pensions-and-retirement/pension-wise' },
        ],
        fynPrompt: 'Can you help me plan my financial goals and work out how much I need to save?',
    },
];

export default learnTopics;

export function getTopicById(topicId) {
    return learnTopics.find(t => t.id === topicId) || null;
}
