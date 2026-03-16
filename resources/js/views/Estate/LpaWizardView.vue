<template>
  <AppLayout>
    <div class="max-w-3xl mx-auto py-6 px-4">
      <LpaWizard
        :lpa-type="lpaType"
        :edit-id="editId"
        @complete="handleComplete"
        @cancel="handleCancel"
      />
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import LpaWizard from '@/components/Estate/LpaWizard.vue';

export default {
  name: 'LpaWizardView',

  components: {
    AppLayout,
    LpaWizard,
  },

  computed: {
    lpaType() {
      return this.$route.params.type || 'property_financial';
    },
    editId() {
      return this.$route.query.edit ? parseInt(this.$route.query.edit) : null;
    },
  },

  methods: {
    handleComplete() {
      this.$router.push('/estate');
    },
    handleCancel() {
      this.$router.push('/estate');
    },
  },
};
</script>
