#!/bin/bash
# Stop hook: Check for hardcoded tax values in app code
# Runs after every session to catch any newly introduced hardcoded tax values

cd /Users/CSJ/Desktop/fynla

# Only check files that have been modified (staged or unstaged)
CHANGED_FILES=$(git diff --name-only HEAD 2>/dev/null; git diff --cached --name-only 2>/dev/null)

if [ -z "$CHANGED_FILES" ]; then
  exit 0
fi

VIOLATIONS=""

for file in $CHANGED_FILES; do
  # Only check .vue, .js, .php files (skip tests, seeders, config comments, data files)
  case "$file" in
    *.vue|*.js|*.php) ;;
    *) continue ;;
  esac
  case "$file" in
    tests/*|database/seeders/*|*.json|resources/js/constants/taxConfig.js|resources/js/utils/dateFormatter.js|resources/js/views/Version.vue) continue ;;
  esac

  [ ! -f "$file" ] && continue

  # Check for hardcoded tax year strings in non-comment lines
  # Exclude: comments, dynamic functions, JSDoc, historical carry-forward references
  HITS=$(grep -n "'20[2-3][0-9]/[0-9][0-9]'" "$file" 2>/dev/null | grep -v "//\|getTaxYear\|getCurrentTaxYear\|TaxConfigService\|@param\|@return\|e\.g\.\|example\|getHistorical\|carryForward\|carry_forward\|previous.*year" || true)
  if [ -n "$HITS" ]; then
    VIOLATIONS="$VIOLATIONS\n  $file: hardcoded tax year string\n$HITS"
  fi

  # Check for hardcoded ISA/pension allowance values in data() or assignments
  HITS2=$(grep -n "ISA_ALLOWANCE: 20000\|ISA_ALLOWANCE = 20000\|personalAllowance = 12570\|cgtAllowance = 3000\|niThreshold = 12570" "$file" 2>/dev/null || true)
  if [ -n "$HITS2" ]; then
    VIOLATIONS="$VIOLATIONS\n  $file: hardcoded tax value\n$HITS2"
  fi
done

if [ -n "$VIOLATIONS" ]; then
  echo "{\"systemMessage\": \"WARNING: Hardcoded tax values detected in changed files. Rule: NEVER hardcode tax years or financial values. Use TaxConfigService (PHP) or getCurrentTaxYear()/taxConfig.js (frontend).\"}"
  exit 0
fi

exit 0
