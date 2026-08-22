<template>
  <div class="bg-white rounded-lg shadow-sm border border-light-gray p-6">
    <div class="text-center mb-8">
      <div class="w-16 h-16 bg-spring-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
      </div>
      <h2 class="text-h3 font-bold text-horizon-500 mb-2">Your Will Has Been Generated</h2>
      <!-- W-0143. This said "Follow the steps below to make it legally binding" under
           the heading "How to Make Your Will Legally Valid". Wills Act 1837 s.9(1)
           opens "No will shall be valid unless", stating NECESSARY conditions; both
           strings stated a SUFFICIENT one, which is the converse. "until it is signed
           and witnessed" restores the necessary form, and "the parts you can prepare
           for" stops the list reading as exhaustive without enumerating what it omits
           — limb (b), that the testator appears to have intended their signature to
           give effect to the will, is a state of mind and no checklist reaches it.
           Do not swap "binding" for "valid" or back: both assert the object, and the
           fix is the sentence shape, not the adjective. -->
      <p class="text-sm text-neutral-500">
        Your will has been saved. It is a draft until it is signed and witnessed.
        Section 9 of the Wills Act 1837 sets out what the law requires; the steps below
        cover the parts you can prepare for, and Fynla does not check any of them.
      </p>
    </div>

    <!-- Signing Instructions -->
    <div class="space-y-4">
      <h3 class="text-base font-semibold text-horizon-500">Before your will can take effect</h3>

      <div class="border border-light-gray rounded-lg divide-y divide-light-gray">
        <div class="p-4 flex gap-4">
          <div class="w-8 h-8 bg-raspberry-100 text-raspberry-600 rounded-full flex items-center justify-center flex-shrink-0 font-bold text-sm">1</div>
          <div>
            <h4 class="text-sm font-semibold text-horizon-500">Print Your Will</h4>
            <p class="text-sm text-neutral-500 mt-1">Print two copies of your will using the button below. Use good quality paper.</p>
          </div>
        </div>

        <div class="p-4 flex gap-4">
          <div class="w-8 h-8 bg-raspberry-100 text-raspberry-600 rounded-full flex items-center justify-center flex-shrink-0 font-bold text-sm">2</div>
          <div>
            <h4 class="text-sm font-semibold text-horizon-500">Do Not Sign Yet</h4>
            <p class="text-sm text-neutral-500 mt-1">Do not sign your will until both witnesses are present with you. All three of you must sign during the same session.</p>
          </div>
        </div>

        <div class="p-4 flex gap-4">
          <div class="w-8 h-8 bg-raspberry-100 text-raspberry-600 rounded-full flex items-center justify-center flex-shrink-0 font-bold text-sm">3</div>
          <div>
            <h4 class="text-sm font-semibold text-horizon-500">Choose Two Independent Witnesses</h4>
            <p class="text-sm text-neutral-500 mt-1">Your witnesses must be:</p>
            <!-- W-0157. "18 years or older" was stated as a requirement and is
                 unsourced — s.9 sets no witness age, and compliance could not
                 establish where the figure came from. It is not asserted to be
                 wrong; it is unverified, so it says less and says whose suggestion
                 it is. Do not restore a number without a source. -->
            <ul class="text-sm text-neutral-500 mt-2 space-y-1 list-disc ml-5">
              <li>Adults</li>
              <li><strong>Not</strong> a beneficiary named in your will</li>
              <li><strong>Not</strong> the spouse or civil partner of a beneficiary</li>
              <li>Of sound mind</li>
            </ul>
            <p class="text-xs text-neutral-500 mt-2">
              The Wills Act 1837 does not set an age for witnesses. Suggesting adults is
              Fynla's guidance, not a legal requirement.
            </p>
            <p class="text-xs text-raspberry-600 mt-2">
              If a beneficiary or their spouse witnesses your will, their inheritance is automatically void.
            </p>
          </div>
        </div>

        <div class="p-4 flex gap-4">
          <div class="w-8 h-8 bg-raspberry-100 text-raspberry-600 rounded-full flex items-center justify-center flex-shrink-0 font-bold text-sm">4</div>
          <div>
            <h4 class="text-sm font-semibold text-horizon-500">Sign Together</h4>
            <p class="text-sm text-neutral-500 mt-1">With both witnesses present:</p>
            <ol class="text-sm text-neutral-500 mt-2 space-y-1 list-decimal ml-5">
              <li>You sign the will in front of both witnesses</li>
              <li>Each witness signs in front of you and each other</li>
              <li>Each witness prints their name, address, occupation, and the date</li>
            </ol>
          </div>
        </div>

        <div class="p-4 flex gap-4">
          <div class="w-8 h-8 bg-raspberry-100 text-raspberry-600 rounded-full flex items-center justify-center flex-shrink-0 font-bold text-sm">5</div>
          <div>
            <h4 class="text-sm font-semibold text-horizon-500">Store Safely</h4>
            <p class="text-sm text-neutral-500 mt-1">Keep one signed copy in a safe, fireproof location. Give the second copy to your executor. Tell your executor where the original is stored.</p>
            <p class="text-xs text-neutral-500 mt-2">Optional: You can also store your will with the Probate Service for a one-off charge of {{ willStorageFee }} — visit <strong>gov.uk</strong> for details.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="flex flex-col sm:flex-row justify-between items-center gap-4 mt-8 pt-6 border-t border-light-gray">
      <button
        @click="handlePrint"
        class="px-6 py-2.5 bg-raspberry-500 text-white rounded-lg font-medium hover:bg-raspberry-600 transition-colors flex items-center gap-2"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
        </svg>
        Print / Save as PDF
      </button>
      <router-link
        to="/estate"
        class="px-4 py-2 text-neutral-500 hover:text-horizon-500 transition-colors text-sm"
      >
        Return to Estate Planning
      </router-link>
    </div>
  </div>
</template>

<script>
import { printWillDocument } from '@/utils/willDocumentRenderer';

/**
 * The charge to deposit a will with the Probate Service.
 *
 * Source: HM Courts and Tribunals Service, "How to store a will with the Probate
 * Service" — "There is a one-off charge of £24 to deposit a will or its codicil."
 * Read 2026-08-21; the page displayed "Updated 13 July 2026", so this figure is
 * re-checkable against a dated publisher page. Registered as row **C3** in
 * `workforce/core/registry/sources.md`, which carries the re-check trigger.
 *
 * W-0157: Fynla stated **£75** until 2026-08-21 — wrong by more than a factor of
 * three, and in the direction that makes a service look unaffordable when it is not.
 * It was inline, unsourced and undated, which is why nothing caught it. This is the
 * third figure of that shape found in one day.
 *
 * **If this number changes, update `sources.md` row C3 in the same edit.** A number
 * here without a matching dated row there is how the last one went stale.
 */
const WILL_STORAGE_FEE = '£24';

export default {
  name: 'WillBuilderSigningStep',

  props: {
    formData: { type: Object, required: true },
  },

  data() {
    return {
      willStorageFee: WILL_STORAGE_FEE,
    };
  },

  methods: {
    handlePrint() {
      printWillDocument(this.formData);
    },
  },
};
</script>
