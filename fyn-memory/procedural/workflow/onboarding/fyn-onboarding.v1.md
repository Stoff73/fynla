---
procedure_id: 'onboarding.workflow.fyn-onboarding'
kind: workflow
module: onboarding
version: 1
active: true
effective_from: 2026-06-02
---

Fyn onboarding state-machine transition table — DATA subset only.

PHP-only fields (callable `next`, callable `prompt_text`, `skip_if`) stay in
`OnboardingStateMachine` and are re-attached by state id at merge time; the
`{branch: …}` / `{builder: …}` markers below are descriptive only and are
NEVER read as the actual transition target / prompt. See
`docs/superpowers/plans/2026-06-02-coala-phase-4d-onboarding-workflow.md`.

NO ICONS: bubbles are `{id, label}` (+ optional `description`) only.

```yaml
path_choice:
  turn_type: bubbles
  prompt_text: "Hi {first_name}, I'm Fyn — welcome to Fynla. I'll help you set up your financial plan. To start, do you want to follow a life-stage journey or pick a single module focus?"
  bubbles:
    - { id: journey, label: 'Follow a journey' }
    - { id: focus, label: 'Pick a focus' }
  capture_field: onboarding_fyn_path
  next: { branch: nextFromPathChoice }

journey_selection:
  turn_type: bubbles
  prompt_text: 'Which journey fits your situation best?'
  bubbles:
    - { id: budgeting, label: 'Starting Out', description: 'Build smart money habits from day one.' }
    - { id: goals, label: 'Building Foundations', description: 'Save for your first home and grow your career.' }
    - { id: protection, label: 'Protecting What Matters', description: 'Secure your family and grow your wealth.' }
    - { id: retirement, label: 'Planning Your Future', description: 'Maximise your wealth and prepare for retirement.' }
    - { id: estate, label: 'Enjoying Your Wealth', description: 'Make your money last and leave a legacy.' }
  capture_field: onboarding_fyn_selection
  next: base_personal

focus_selection:
  turn_type: bubbles
  prompt_text: 'Which area would you like me to focus on first?'
  bubbles:
    - { id: savings, label: 'Savings' }
    - { id: investment, label: 'Investment' }
    - { id: retirement, label: 'Retirement' }
    - { id: protection, label: 'Protection' }
    - { id: estate, label: 'Estate Planning' }
    - { id: goals, label: 'Goals & Life Events' }
    - { id: budgeting, label: 'Budgeting' }
    - { id: business, label: 'Business' }
  capture_field: onboarding_fyn_selection
  next: base_personal

base_personal:
  turn_type: grouped_extract
  prompt_text: { builder: buildPersonalPrompt }
  extraction_tool: capture_personal_details
  retry_text: "Sorry, I didn't catch both pieces. Could you tell me your date of birth (something like 12 January 1985) and your marital status?"
  next: { branch: nextFromPersonal }

base_spouse:
  turn_type: grouped_extract
  prompt_text: { builder: buildSpousePrompt }
  extraction_tool: capture_spouse_details
  retry_text: 'I need a first name, date of birth, and email address for your partner so I can create and link their account. Could you share those again?'
  next: base_dependants
  skip_link: { label: 'Skip this for now', color: raspberry }

base_dependants:
  turn_type: bubbles
  prompt_text: 'Any children or dependants to add?'
  bubbles:
    - { id: yes, label: 'Yes' }
    - { id: 'no', label: 'No' }
  capture_field: null
  next: { branch: nextFromDependants }

base_dependants_detail:
  turn_type: grouped_extract
  prompt_text: 'Lovely. Tell me their first names, ages, and how they are related to you (child, parent, or other dependant). You can list several in one go.'
  extraction_tool: capture_dependants
  retry_text: 'Could you list them again with ages and how they are related? Something like "Alice 7 child, Bob 4 child".'
  next: profile_review_family

profile_review_family:
  turn_type: bubbles
  prompt_text: 'Does your family and personal information look right? Tap the bubble to confirm — or just tell me what needs changing.'
  bubbles:
    - { id: looks_correct, label: 'Looks correct' }
  capture_field: null
  layout: standard
  next: base_employment

base_employment:
  turn_type: bubbles
  prompt_text: "And what's your employment situation at the moment?"
  bubbles:
    - { id: employed, label: 'Full-time' }
    - { id: self_employed, label: 'Self-employed' }
    - { id: part_time, label: 'Part-time' }
    - { id: retired, label: 'Retired' }
    - { id: unemployed, label: 'Not working' }
  capture_field: employment_status
  value_parser: parseEmploymentFromText
  next: { branch: nextFromEmployment }

base_work:
  turn_type: grouped_extract
  prompt_text: { builder: buildWorkPrompt }
  extraction_tool: capture_work_details
  retry_text: 'I need three things: the company or trade name, your position, and your gross annual income in GBP. Could you share all three?'
  next: base_employment_more

base_employment_more:
  turn_type: bubbles
  prompt_text: 'Do you have any other roles or sources of earned income to add?'
  bubbles:
    - { id: yes, label: 'Yes, add another' }
    - { id: 'no', label: "No, that's everything" }
  capture_field: null
  next: { branch: nextFromEmploymentMore }

base_retirement_date:
  turn_type: free_text
  prompt_text: 'When did you retire? A year is fine — something like "2020".'
  capture_field: retirement_date
  value_parser: parseRetirementDate
  next: base_expenditure

base_expenditure:
  turn_type: free_text
  prompt_text: "And roughly how much goes out each month — rent or mortgage, bills, food, transport, the lot? A ballpark figure is fine. I'll use it to work out your savings capacity, emergency fund target, and how much income you'll need in retirement."
  capture_field: monthly_expenditure
  value_parser: parseExpenditureAmount
  next: profile_review_expenditure

profile_review_expenditure:
  turn_type: bubbles
  prompt_text: 'Your expenditure is noted. Confirm the full profile looks right — or tell me what to change.'
  bubbles:
    - { id: looks_correct, label: 'Looks correct' }
  capture_field: null
  layout: standard
  next: { branch: nextFromExpenditureReview }

campaign_intro:
  turn_type: bubbles
  prompt_text: { builder: buildCampaignIntroPrompt }
  bubbles:
    - { id: okay, label: 'Okay' }
    - { id: nope, label: 'Nope' }
  capture_field: null
  next: { branch: nextFromCampaignIntro }

campaign_occupational_scheme:
  turn_type: delegated
  prompt_text: "Tell me about your workplace pension. What percentage of your salary do you contribute, does your employer match it, and is it via salary sacrifice? If you don't have a workplace pension, just say so and we'll move on."
  capture_field: null
  next: campaign_isa_holdings

campaign_isa_holdings:
  turn_type: delegated
  prompt_text: "Let's look at your ISAs. Do you have a Cash ISA or Stocks & Shares ISA? If so, what's the current balance and how much have you put in this tax year?"
  capture_field: null
  next: campaign_bank_accounts

campaign_bank_accounts:
  turn_type: delegated
  prompt_text: "Now your savings outside an ISA — bank accounts, savings accounts, premium bonds. For each, what's the balance and the interest rate?"
  capture_field: null
  next: campaign_investment_accounts

campaign_investment_accounts:
  turn_type: delegated
  prompt_text: 'Any investment accounts outside an ISA — General Investment Accounts, share trading platforms? If so, current value, your purchase cost, and any annual dividend income.'
  capture_field: null
  next: campaign_pension_contribs

campaign_pension_contribs:
  turn_type: delegated
  prompt_text: 'Beyond the workplace pension we covered, do you make any personal pension or Self-Invested Personal Pension contributions? If so, how much per year (gross)?'
  capture_field: null
  next: campaign_pension_history

campaign_pension_history:
  turn_type: grouped_extract
  prompt_text: "Quick one — to check if you have any unused pension allowance to top up, what did you contribute (gross) in each of the last 3 tax years? If you don't know exact numbers, rough figures are fine, and \"zero\" is a valid answer."
  capture_field: null
  extraction_tool: capture_pension_history
  retry_text: 'I just need a rough gross figure for each of the last three tax years (2024/25, 2023/24, 2022/23). Even "I think it was about 5,000 each year" works.'
  next: campaign_charitable_giving

campaign_charitable_giving:
  turn_type: grouped_extract
  prompt_text: "One more — do you make any charitable donations through Gift Aid? If you donate at the higher or additional rate, there's extra relief you can reclaim. Roughly how much per year? Say \"none\" if you don't donate."
  capture_field: null
  extraction_tool: capture_charitable_giving
  retry_text: 'Just an annual figure works — e.g. "about £500" or "none".'
  next: campaign_spouse_work

campaign_spouse_work:
  turn_type: bubbles
  prompt_text: 'Does your spouse work?'
  bubbles:
    - { id: yes, label: 'Yes, they work' }
    - { id: 'no', label: "No, they don't currently work" }
  capture_field: null
  bubble_capture:
    tool: capture_spouse_work_status
    input_for_bubble:
      yes: { spouse_works: true }
      'no': { spouse_works: false }
  next: { branch: nextFromSpouseWork }

campaign_spouse_household:
  turn_type: grouped_extract
  prompt_text: 'Great. How much does your spouse earn annually, and do they have ISAs, investments, or pension contributions of their own?'
  capture_field: null
  extraction_tool: capture_spouse_household_data
  retry_text: 'I need their annual income and whatever you know about their ISA / investment / pension balances. Could you share what you have?'
  next: campaign_terminal

campaign_spouse_non_working_assets:
  turn_type: grouped_extract
  prompt_text: "Got it — your spouse doesn't currently earn an income. That's actually useful for your tax strategy, because they have around £40,000 of unused tax allowances we can put to work. Do they have any savings, ISAs, or investment accounts in their own name today, or is it all in yours?"
  capture_field: null
  extraction_tool: capture_spouse_non_working_assets
  retry_text: 'Just give me rough numbers — savings balance, ISA balance, investment balance. If they have nothing in their own name, just say "nothing".'
  next: campaign_terminal

campaign_terminal:
  turn_type: terminal
  prompt_text: 'All set, {first_name} — let me show you your tax position.'
  capture_field: null
  navigate_to: /tax-strategy
  next: done

asset_capture:
  turn_type: delegated
  prompt_text: { builder: buildAssetCaptureIntro }
  capture_field: null
  next: add_more

add_more:
  turn_type: bubbles
  prompt_text: "Anything else you'd like to cover?"
  bubbles:
    - { id: savings, label: 'Savings' }
    - { id: investment, label: 'Investment' }
    - { id: retirement, label: 'Retirement' }
    - { id: protection, label: 'Protection' }
    - { id: done, label: "I'm done" }
  capture_field: null
  next: { branch: nextFromAddMore }

done:
  turn_type: terminal
  prompt_text: 'All set, {first_name}. Your {selection} module is ready to explore.'
  capture_field: null
  next: null
```
