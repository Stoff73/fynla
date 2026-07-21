---
pointer_id: isa-annual-allowance
topic: ISA and pension annual allowances
triggers: [isa, allowance, subscription, contribute, pension annual]
mode: both
handler: tax_allowance
source_label: TaxConfigService and saved ISA records
version: 2
---

Use when the user asks how much they can pay into an ISA or pension this year, or
about used or remaining allowance. The annual limits come from TaxConfigService;
ISA usage and the account-level subscription records come from the user's saved
data. Never state an allowance or remaining amount from memory.
