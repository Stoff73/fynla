This is the parent file
@plansDetail

Everything that follows is updates and amendments to teh plans section as detail in @planDetail.md. You must use /frontend-dev:feature-dev when implementing these as well /frontend-design always refering to @designStyle.md to ensure correct implementation.

Overall planning section changes:

2.10 describes hardcoded constants and rates, this is WRONG and breaks the rules of the application. All values, rates, returns, risks etc must be taken from either the central taxconfig file or the user data, it must be personal and dynamic. The TTL cache can be set, but this ust be done via a central config file, which is accessable to admin, this also includes the age gate for estate plans of 35.

We must integrate the goal planning system into each plan. In order to do this each goal must have an account allocated to it, which will allow us to add it to an appropriate plan. So if it is to increase retirement savings, we attache it to a retirement account, etc..

If there is a goal associated with a plan, this is the priority with all other recommendation secondary

Investment & Savings Plan

In savings, for the emergency fund, if the emergency fund is larger than 6 months, and the cash accounts have not been allocated to any goals to reduce this over time, we must recommend moving this money into the various tax wrappers, so isa if available, then pension if avalailbe, then bond, then gifting.

This plan, as well as the retirement plan, must use the users disposable income figure in the plan when giving recommendations, no made up numbers or hard coded issues. The users disposable income must be allocated to a temporary distribution account, where all the agents for the investments, savings and retirement plans can draw from, When an amount is allocated to an account the account is reduced, so we do not double count, and we do nto exceed the affordability of the user. The retirement plan uses the whole account, and the investments & savings plan use the whole account, as these are separate plans.

Projections must either be to goal length if assigned or to retirement no 10 year default

generateRecommendation change the following:
1. we must ask for the information that is missing for us to calculate the risk profile, not for the user to complete their risk profile
2. if there are no holdings, state that we are defaulting to 'Risk based fee optimised allocations'

Estate Plan

why do we have to check all info again and do the calcs, why can't the information be fetched from the Estate Module? We already have a full table, as well as all the strategies, recommendations and calcs. This should be fetched from one place, not redone

We need to show this as a joint view if the user is married an there is spouse data and/or a linked account
The plan needs more detail, especially ion what to do, what if scenarios and side by side comparisons. Using the data from the estate module will help.
We also need to show where the money is coming from for any charitable or gifting recommendations, and esnure that all life ocver recommendations are affordable

Get rid of the score mechanism in the estate plan, again this violates a very distinct rule? 

Holistic Plan
 Instead of looking across all modules, again duplicating work that is already done? Look across the plan, to get the relevant infomration, this way theu will match and not cause confusion. Then identifies conflicts between recommendations, ranks all recommendations by priority, allocates available cash flow surplus across competing demands, and produces an integrated action plan.

 With goals in the plans these should now be included, as well as the estate plan.

 The one difference will be the temporary allocation account for the disposable income, which will now be shared between all goals and not reset like for retirement or investment & savings, so these will need to be prioritised.

 Lastly we need to get ris of section 10, legacy plans, all references, code, files etc.. as it is no longer needed. 
