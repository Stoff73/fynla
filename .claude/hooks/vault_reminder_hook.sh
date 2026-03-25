#!/bin/bash
# Post-edit hook: reminds Claude to check vault context when editing module files
filepath="$CLAUDE_FILE_PATH"

module=""
case "$filepath" in
  *Services/Investment*|*components/Investment*|*views/Investment*|*InvestmentList*|*InvestmentDetail*)
    module="investment" ;;
  *Services/Estate*|*components/Estate*|*EstateDashboard*|*IHT*|*Trust*|*Gift*)
    module="estate" ;;
  *Services/Protection*|*components/Protection*|*ProtectionDashboard*|*PolicyForm*)
    module="protection" ;;
  *Services/Retirement*|*components/Retirement*|*Pension*|*StatePension*)
    module="retirement" ;;
  *Services/Savings*|*components/Savings*|*SavingsDashboard*|*CashOverview*)
    module="savings" ;;
  *Services/Goals*|*components/Goals*|*GoalsDashboard*|*LifeEvent*)
    module="goals" ;;
  *components/NetWorth/Property*|*PropertyForm*|*PropertyCard*|*PropertyList*)
    module="property" ;;
  *deploy/*|*Deploy*|*.htaccess)
    module="deployment" ;;
  *components/Admin*|*AdminController*|*AdminPanel*)
    module="admin" ;;
  *HasAiChat*|*AiChat*|*XaiTool*|*XaiClient*|*CoordinatingAgent*)
    module="ai-chat" ;;
  *fynlaDesignGuide*|*designSystem*)
    module="design-system" ;;
  *Services/Tax*|*TaxConfig*|*UKTax*|*FinancialCalculation*)
    module="tax" ;;
  *Onboarding*|*onboarding*)
    module="onboarding" ;;
  *Payment*|*Checkout*|*Subscription*|*Revolut*)
    module="payments" ;;
esac

if [ -n "$module" ]; then
  echo "VAULT: Editing $module module. Ensure context loaded (/vault-context $module)."
fi
