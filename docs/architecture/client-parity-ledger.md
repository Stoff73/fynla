# iOS and `/m` client parity ledger

This is the release-blocking traceability record for the approved iOS and
`/m` debugging programme. It supersedes the July native-migration package
ledger. Historical PR evidence remains in `docs/testing` and
`docs/superpowers/evidence`; this matrix records the current contract and the
fresh PR7 closure gate.

## Authority invariants

- Laravel rehydrates existing financial facts from canonical records after
  authenticating and authorising the requesting user.
- Clients send identifiers and proposed changes, never authoritative balances
  or financial facts as conversation context.
- One canonical portfolio exposure and drift method serves DC pensions,
  investments and Stocks & Shares ISAs. It compares actual look-through
  exposure with both an entered portfolio and, when available, a recommended
  allocation, and reports unclassified exposure and provenance.
- Recorded history never contains projected values. Forecasts are separately
  labelled, use server-owned assumptions and persist through the canonical API.
- Semantic destinations are allowlisted. Unknown or unauthorised resources
  fail safely without leaking another user's facts.

## Closure status

`pending-ci` means the durable surface coverage and installed-Google-Chrome
journey are green, but the fresh PR7 native journey is awaiting the simulator
CI gate because the local CoreSimulator host failed before Fynla launched.
`green` is permitted only after that CI journey, including its largest Dynamic
Type rerun, passes. Platform-specific behavior is called out explicitly in its
evidence cell.

## Machine evidence registry

| Key | Surface | Existing path |
|---|---|---|
| L-AUTH | Laravel authority | `tests/Feature/AI/ContextualConversationContractTest.php` |
| L-CONTRACTS | Laravel client contracts | `tests/Feature/Contracts` |
| L-HISTORY | Laravel history | `tests/Feature/History/BalanceHistoryEntitlementTest.php` |
| L-MOBILE | Laravel mobile presentation | `tests/Feature/Mobile/MobileAchievementsTest.php` |
| L-PORTFOLIO | Laravel exposure and drift | `tests/Unit/Services/Investment/PortfolioExposureServiceTest.php` |
| L-PROJECTION | Laravel projections | `tests/Unit/RetirementProjectionContractServiceTest.php` |
| M-NAV | `/m` navigation | `resources/mobile/navigation/__tests__/semanticDestinations.spec.js` |
| M-PROJECTION | `/m` projections | `tests/frontend/mobile/NetWorthForecast.test.js` |
| M-VIEWS | `/m` presentation | `resources/mobile/views/__tests__` |
| I-UNIT | iOS models and services | `ios-native/FynlaTests` |
| I-UI | iOS user journeys | `ios-native/FynlaUITests/FynlaUITests.swift` |
| U-CHROME | Installed-Chrome closure | `tests/E2E/mobile/parity-closure.spec.js` |
| U-NATIVE | Native closure | `ios-native/FynlaUITests/FynlaUITests.swift` |
| E-PR7 | PR7 execution record | `docs/superpowers/evidence/2026-08-11-pr7-ios-m-parity-closure.md` |

<!-- PARITY-CLOSURE-START -->

| ID | Requirement | Laravel authority | `/m` automated evidence | iOS automated evidence | PR7 user-loop evidence | Evidence keys | Status |
|---|---|---|---|---|---|---|---|
| M-01 | Admin access | Admin handoff and authorization feature contracts | navigation model and Settings route specs | admin drawer and web-handoff UI tests | drawer audit; admin remains an authorized web handoff | L-CONTRACTS M-NAV I-UNIT I-UI U-CHROME U-NATIVE E-PR7 | pending-ci |
| M-02 | Personal Information | canonical profile presentation and contextual ownership contracts | `PersonalInformation.spec.js` | `PersonalInformationTests` and drawer UI test | shared drawer route and stable screen identity | L-AUTH M-VIEWS I-UNIT I-UI U-CHROME U-NATIVE E-PR7 | pending-ci |
| M-03 | Subscription | canonical entitlement and billing-management APIs | `Subscription.spec.js` | subscription API/model/UI tests | shared drawer route and bounded entitlement state | L-CONTRACTS M-VIEWS I-UNIT I-UI U-CHROME U-NATIVE E-PR7 | pending-ci |
| M-04 | Help, privacy, terms and legal | allowlisted handoff contract | Settings navigation specs | settings and web-handoff tests | settings audit; external legal pages use secure handoff | L-CONTRACTS M-NAV I-UNIT I-UI U-CHROME U-NATIVE E-PR7 | pending-ci |
| M-05 | Preferences | notification and account settings APIs | `Settings.spec.js` | Settings, privacy and push suites | Settings route and controls remain reachable | L-CONTRACTS M-VIEWS I-UNIT I-UI U-CHROME U-NATIVE E-PR7 | pending-ci |
| M-06 | Dashboard text | `MobileDashboardAggregator` presentation contract | `Dashboard.spec.js` | dashboard model and UI suites | current dashboard heading, level and recommendation | L-CONTRACTS M-VIEWS I-UNIT I-UI U-CHROME U-NATIVE E-PR7 | pending-ci |
| M-07 | Recommendation routing | server-authored semantic destination contract | semantic destination and dashboard specs | router and recommendation UI tests | retirement recommendation opens Retirement | L-CONTRACTS M-NAV I-UNIT I-UI U-CHROME U-NATIVE E-PR7 | pending-ci |
| M-08 | Overview Add and canonical detail Edit | contextual resource ownership and rehydration contracts | overview/detail contextual-authority specs | contextual edit and canonical detail UI journeys | overview/detail action audit across financial routes | L-AUTH M-VIEWS I-UNIT I-UI U-CHROME U-NATIVE E-PR7 | pending-ci |
| M-09 | Protection gaps and explanations | protection presentation service/API tests | `FinancialDataParity.spec.js` | protection model and PR4 UI journey | Protection route plus canonical explanation regression | L-CONTRACTS M-VIEWS I-UNIT I-UI U-CHROME U-NATIVE E-PR7 | pending-ci |
| M-10 | Canonical detail headings | module detail APIs expose canonical labels | canonical detail component specs | detail model/UI and heading regressions | detail-route screen identity audit | L-CONTRACTS M-VIEWS I-UNIT I-UI U-CHROME U-NATIVE E-PR7 | pending-ci |
| M-11 | Fresh contextual Fyn conversations | contextual creation, retry and rehydration contracts | contextual conversation specs | contextual/history UI journey | fresh IDs and exact transcript reopen retained | L-AUTH M-VIEWS I-UNIT I-UI U-CHROME U-NATIVE E-PR7 | pending-ci |
| M-12 | Retirement holdings, allocation, performance, drift, projections and income | retirement presentation, projection and portfolio services | financial-data and projection suites | retirement, investment and projection suites | age bands, 4.7% assumption and portfolio states | L-PORTFOLIO L-PROJECTION M-PROJECTION M-VIEWS I-UNIT I-UI U-CHROME U-NATIVE E-PR7 | pending-ci |
| M-13 | Bank Accounts naming and route | savings API and navigation contract | router, navigation and savings specs | navigation menu and savings suites | shared `Bank Accounts` drawer label/route | L-CONTRACTS M-NAV M-VIEWS I-UNIT I-UI U-CHROME U-NATIVE E-PR7 | pending-ci |
| M-14 | Bank account editing behavior | savings ownership and contextual-write boundary | overview/detail authority specs | savings detail contextual UI journey | Add remains overview-only; Edit remains detail-only | L-AUTH M-VIEWS I-UNIT I-UI U-CHROME U-NATIVE E-PR7 | pending-ci |
| M-15 | Freemium enforcement | canonical stores and `TierGate` write boundaries | typed limit presentation specs | API error and subscription routing suites | route audit plus server negative regressions | L-CONTRACTS M-VIEWS I-UNIT I-UI U-CHROME U-NATIVE E-PR7 | pending-ci |
| M-16 | ISA type and ownership | owner-aware savings/investment presentation contracts | financial-data parity specs | savings and investment model/UI suites | canonical ISA labels and ownership contract | L-PORTFOLIO M-VIEWS I-UNIT I-UI U-CHROME U-NATIVE E-PR7 | pending-ci |
| M-17 | Current and prior ISA contributions | canonical ISA contribution ledger | financial-data parity specs | PR4 prior-year contribution journey | current/prior tax-year contract retained | L-CONTRACTS M-VIEWS I-UNIT I-UI U-CHROME U-NATIVE E-PR7 | pending-ci |
| M-18 | ISA allowance breakdown | server-owned allowance tracker and normalized tax year | financial-data parity specs | savings allowance model/UI tests | contribution and allowance reconciliation regression | L-CONTRACTS M-VIEWS I-UNIT I-UI U-CHROME U-NATIVE E-PR7 | pending-ci |
| M-19 | Goals overview, details and actions | canonical goal APIs and ownership-scoped contextual actions | goals overview/detail specs | goals model and canonical detail tests | shared Goals route and stable detail contract | L-AUTH L-CONTRACTS M-VIEWS I-UNIT I-UI U-CHROME U-NATIVE E-PR7 | pending-ci |
| M-20 | Holistic Plan | composite-plan API, ranking and entitlement gate | `HolisticPlan.spec.js` | `HolisticPlanTests` and PR3 UI journey | shared route and bounded server-authored plan state | L-CONTRACTS M-VIEWS I-UNIT I-UI U-CHROME U-NATIVE E-PR7 | pending-ci |
| M-21 | Personalised achievements | v2 achievement presentation service | `Achievements.spec.js` and installed-Chrome E2E | achievement model/client and PR6 UI journey | earned/reached/progress/inapplicable/action states | L-MOBILE M-VIEWS I-UNIT I-UI U-CHROME U-NATIVE E-PR7 | pending-ci |
| M-22 | Net Worth heading | canonical Net Worth presentation API | Net Worth component/navigation specs | Net Worth model/UI tests | exact `Net Worth` shared route and heading | L-CONTRACTS M-NAV M-VIEWS I-UNIT I-UI U-CHROME U-NATIVE E-PR7 | pending-ci |
| M-23 | Recorded and projected Net Worth | history and forecast services keep separate contracts | projection and balance-history specs | balance-history and forecast suites | recorded/projected copy plus save/reload/reset | L-HISTORY L-PROJECTION M-PROJECTION I-UNIT I-UI U-CHROME U-NATIVE E-PR7 | pending-ci |
| M-24 | Net Worth editing | contextual action and forecast-assumption APIs | contextual and forecast specs | Net Worth UI and forecast tests | identifier-only Fyn action and canonical assumption write | L-AUTH L-PROJECTION M-PROJECTION I-UNIT I-UI U-CHROME U-NATIVE E-PR7 | pending-ci |
| M-25 | Property detail and mortgage amount | joint-aware property/mortgage read stores | Net Worth detail navigation specs | property/mortgage model and PR3 journeys | canonical linked detail contract retained | L-CONTRACTS M-VIEWS I-UNIT I-UI U-CHROME U-NATIVE E-PR7 | pending-ci |
| M-26 | Net Worth links reuse canonical details | semantic detail destination contract | Net Worth detail navigation specs | AppRouter and canonical detail UI tests | reused Property, Mortgage and Liability destinations | L-CONTRACTS M-NAV M-VIEWS I-UNIT I-UI U-CHROME U-NATIVE E-PR7 | pending-ci |
| M-27 | Valuables | canonical chattel category and detail contract | module and Net Worth category specs | Net Worth category model/UI tests | `Valuables` route/copy audit | L-CONTRACTS M-VIEWS I-UNIT I-UI U-CHROME U-NATIVE E-PR7 | pending-ci |
| M-28 | Liabilities and mortgage reuse | joint-aware liability/mortgage read contracts | Net Worth detail navigation specs | mortgage/liability detail suites | shared canonical debt destinations | L-CONTRACTS M-VIEWS I-UNIT I-UI U-CHROME U-NATIVE E-PR7 | pending-ci |
| M-29 | Income details and tax position | profile income presentation and server-owned tax calculation | income detail specs | income model and PR3 UI journey | canonical Income route/detail contract | L-CONTRACTS M-VIEWS I-UNIT I-UI U-CHROME U-NATIVE E-PR7 | pending-ci |
| M-30 | Expenditure reconciliation and prompt | reconciled active-total presentation service | expenditure detail specs | expenditure model and PR3 UI journey | canonical mode/basis and contextual action contract | L-AUTH L-CONTRACTS M-VIEWS I-UNIT I-UI U-CHROME U-NATIVE E-PR7 | pending-ci |
| M-31 | Contextual action context | strict identifier/destination validation and server rehydration | contextual authority specs | contextual Fyn models and UI journey | clients transmit action/resource/navigation identifiers only | L-AUTH M-NAV M-VIEWS I-UNIT I-UI U-CHROME U-NATIVE E-PR7 | pending-ci |
| M-32 | Conversation History | ownership-authorized summary/transcript APIs | `ConversationHistory.spec.js` | conversation history model/UI suites | exact conversation reopen and safe unavailable fallback | L-AUTH M-VIEWS I-UNIT I-UI U-CHROME U-NATIVE E-PR7 | pending-ci |
| M-33 | Bug reporting | authenticated diagnostic submission and redaction contract | `BugReportSheet.spec.js` | bug report model and UI tests | report control is reachable with privacy-safe diagnostics | L-CONTRACTS M-VIEWS I-UNIT I-UI U-CHROME U-NATIVE E-PR7 | pending-ci |
| M-34 | One allocation and drift algorithm across DC pensions, investments and S&S ISAs | canonical portfolio exposure/presentation services and look-through tests | shared `CanonicalPortfolio` rendering suite | investment/retirement model and PR4 UI journey | entered/recommended comparisons, coverage and provenance | L-PORTFOLIO M-VIEWS I-UNIT I-UI U-CHROME U-NATIVE E-PR7 | pending-ci |

<!-- PARITY-CLOSURE-END -->

## Evidence packages

- PR1 foundations: `docs/testing/2026-08-09-ios-m-parity-pr1-evidence.md`
- PR2 contextual Fyn/history: `docs/testing/2026-08-10-contextual-fyn-conversation-history-evidence.md`
- PR3 canonical details: `docs/testing/2026-08-10-canonical-details-ios-m-evidence.md`
- PR4 financial parity: `docs/testing/2026-08-10-financial-data-parity-evidence.md`
- PR5 projections: `docs/testing/2026-08-10-projection-parity-evidence.md`
- PR6 achievements: `docs/superpowers/evidence/2026-08-11-pr6-personalised-achievements.md`
- PR7 final closure: `docs/superpowers/evidence/2026-08-11-pr7-ios-m-parity-closure.md`
