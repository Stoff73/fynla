<template>
  <!--
    W-0466, CSJ direction 2026-08-24. A PLACEHOLDER, and deliberately so.

    Shares listed on the Alternative Investment Market are not modelled anywhere in
    the app. From 6 April 2026 they attract 50% relief OUTSIDE the Inheritance Tax
    allowance (IHTM25570, Finance Act 2026 Sch 12), and nothing here knows which
    holdings they are: there is no market flag on `holdings` or on
    `investment_accounts`, and a security name cannot be trusted to say so.

    The estate screen's caveat cannot reach this population — it triggers on business
    interests and on farmland, and these shares sit in an investment account. So a
    holder's Inheritance Tax figure is overstated by roughly 20% of the holding
    (50% relief x 40%) and they are told nothing.

    That is why the notice belongs at the point of ENTRY: the user is the one person
    in the system who knows what they are holding.

    Shown unconditionally, because the alternative is guessing. It stays until the
    treatment is modelled, at which point this component is DELETED rather than
    edited — a placeholder that quietly becomes permanent is the failure mode.

    One component rather than a copy per form (Rule 20): there are two holdings entry
    surfaces, `HoldingForm` and `InlineHoldingsEditor`, and a second copy of this
    sentence would drift from the first the moment either is touched.

    Rule 9, as amended by CSJ 2026-08-24: an acronym may be used once it has been
    spelled out to that reader, on the surface they are looking at. This notice names
    the market in full and never abbreviates it, which needs no expansion elsewhere.
  -->
  <div class="bg-eggshell-500 rounded-lg p-4">
    <p class="text-sm text-violet-800">
      Fynla does not yet model shares listed on the Alternative Investment Market.
      If you hold any, your Inheritance Tax estimate may be higher than your actual
      liability — it is worth discussing with a regulated financial adviser or a
      specialist solicitor.
    </p>
  </div>
</template>

<script>
export default {
  name: 'UnmodelledAimNotice',
};
</script>
