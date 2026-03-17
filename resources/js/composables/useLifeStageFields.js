// resources/js/composables/useLifeStageFields.js
import { computed } from 'vue';
import { useStore } from 'vuex';

export function useLifeStageFields(formName, context) {
  const store = useStore();

  const isFieldVisible = (fieldName) => {
    return store.getters['lifeStage/isFieldVisible'](formName, fieldName, context);
  };

  const stageConfig = computed(() => store.getters['lifeStage/formFields'](formName));

  return { isFieldVisible, stageConfig };
}
