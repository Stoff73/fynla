<template>
  <div class="border border-light-gray rounded overflow-hidden">
    <div
      v-if="editor"
      class="flex flex-wrap items-center gap-1 px-2 py-1.5 border-b border-light-gray bg-savannah-100 text-xs"
    >
      <button
        v-for="tool in activeTools"
        :key="tool.id"
        type="button"
        :class="[
          'px-2 py-1 rounded font-medium transition-colors',
          tool.isActive() ? 'bg-horizon-500 text-white' : 'text-horizon-500 hover:bg-white',
        ]"
        :title="tool.label"
        @click="tool.run()"
      >
        <span v-html="tool.icon"></span>
      </button>

      <span v-if="showSize" class="w-px h-5 bg-light-gray mx-1"></span>
      <select
        v-if="showSize"
        class="text-xs bg-white border border-light-gray rounded px-1 py-1 text-horizon-500"
        :value="currentSize"
        @change="setSize($event.target.value)"
        title="Font size"
      >
        <option value="">Normal</option>
        <option value="sm">Small</option>
        <option value="lg">Large</option>
      </select>

      <span v-if="showColor" class="w-px h-5 bg-light-gray mx-1"></span>
      <div v-if="showColor" class="flex items-center gap-1" title="Text colour">
        <button
          v-for="swatch in colorSwatches"
          :key="swatch.id"
          type="button"
          :class="[
            'w-5 h-5 rounded-full border transition-transform',
            swatch.class,
            currentColor === swatch.id ? 'ring-2 ring-offset-1 ring-horizon-500 scale-110' : 'hover:scale-110',
          ]"
          :title="swatch.label"
          @click="setColor(swatch.id)"
        ></button>
        <button
          type="button"
          class="text-xs text-horizon-500 hover:underline ml-1"
          @click="setColor(null)"
          title="Clear colour"
        >
          Clear
        </button>
      </div>
    </div>

    <editor-content
      :editor="editor"
      class="prose prose-sm max-w-none px-3 py-2 min-h-[2.5rem] focus:outline-none"
    />
  </div>
</template>

<script>
import { Editor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import Link from '@tiptap/extension-link';
import { Mark, mergeAttributes } from '@tiptap/core';

// Custom mark that wraps text in a <span class="text-sm|text-lg"> for preset
// sizes. Avoids inline styles (keeps us inside the Tailwind design system and
// the allow-list we enforce in BlockValidator + DOMPurify).
const SIZE_CLASS = { sm: 'text-sm', lg: 'text-lg' };

const FontSize = Mark.create({
  name: 'fontSize',
  addAttributes() {
    return {
      size: { default: null, parseHTML: (el) => el.getAttribute('data-size') || null },
    };
  },
  parseHTML() {
    return [
      { tag: 'span[data-size]' },
      {
        tag: 'span',
        getAttrs: (el) => {
          const cls = el.getAttribute('class') || '';
          if (cls.includes('text-sm')) return { size: 'sm' };
          if (cls.includes('text-lg')) return { size: 'lg' };
          return false;
        },
      },
    ];
  },
  renderHTML({ HTMLAttributes, mark }) {
    const size = mark.attrs.size;
    if (!size || !SIZE_CLASS[size]) return ['span', HTMLAttributes, 0];
    return [
      'span',
      mergeAttributes(HTMLAttributes, { class: SIZE_CLASS[size], 'data-size': size }),
      0,
    ];
  },
});

const COLOR_CLASS = {
  raspberry: 'text-raspberry-500',
  horizon: 'text-horizon-500',
  spring: 'text-spring-500',
  violet: 'text-violet-500',
  neutral: 'text-neutral-500',
};

const TextColor = Mark.create({
  name: 'textColor',
  addAttributes() {
    return {
      color: { default: null, parseHTML: (el) => el.getAttribute('data-color') || null },
    };
  },
  parseHTML() {
    return [
      { tag: 'span[data-color]' },
      {
        tag: 'span',
        getAttrs: (el) => {
          const cls = el.getAttribute('class') || '';
          for (const [id, klass] of Object.entries(COLOR_CLASS)) {
            if (cls.includes(klass)) return { color: id };
          }
          return false;
        },
      },
    ];
  },
  renderHTML({ HTMLAttributes, mark }) {
    const color = mark.attrs.color;
    if (!color || !COLOR_CLASS[color]) return ['span', HTMLAttributes, 0];
    return [
      'span',
      mergeAttributes(HTMLAttributes, { class: COLOR_CLASS[color], 'data-color': color }),
      0,
    ];
  },
});

export default {
  name: 'RichTextEditor',
  components: { EditorContent },
  props: {
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    tools: {
      type: Array,
      default: () => ['bold', 'italic', 'underline', 'link', 'size', 'color'],
    },
  },
  emits: ['update:modelValue'],
  data() {
    return { editor: null };
  },
  computed: {
    showSize() { return this.tools.includes('size'); },
    showColor() { return this.tools.includes('color'); },
    colorSwatches() {
      return [
        { id: 'raspberry', label: 'Raspberry', class: 'bg-raspberry-500 border-raspberry-600' },
        { id: 'horizon', label: 'Horizon', class: 'bg-horizon-500 border-horizon-600' },
        { id: 'spring', label: 'Spring', class: 'bg-spring-500 border-spring-600' },
        { id: 'violet', label: 'Violet', class: 'bg-violet-500 border-violet-600' },
        { id: 'neutral', label: 'Neutral', class: 'bg-neutral-500 border-neutral-600' },
      ];
    },
    currentSize() {
      if (!this.editor) return '';
      const attrs = this.editor.getAttributes('fontSize');
      return attrs?.size || '';
    },
    currentColor() {
      if (!this.editor) return null;
      const attrs = this.editor.getAttributes('textColor');
      return attrs?.color || null;
    },
    activeTools() {
      if (!this.editor) return [];
      const e = this.editor;
      const map = {
        bold: { id: 'bold', label: 'Bold', icon: '<b>B</b>', isActive: () => e.isActive('bold'), run: () => e.chain().focus().toggleBold().run() },
        italic: { id: 'italic', label: 'Italic', icon: '<i>I</i>', isActive: () => e.isActive('italic'), run: () => e.chain().focus().toggleItalic().run() },
        underline: { id: 'underline', label: 'Underline', icon: '<u>U</u>', isActive: () => e.isActive('underline'), run: () => e.chain().focus().toggleUnderline().run() },
        link: { id: 'link', label: 'Link', icon: '🔗', isActive: () => e.isActive('link'), run: () => this.promptLink() },
      };
      return this.tools.filter((t) => map[t]).map((t) => map[t]);
    },
  },
  watch: {
    modelValue(newVal) {
      if (!this.editor) return;
      const current = this.editor.getHTML();
      if (newVal !== current) {
        this.editor.commands.setContent(newVal || '', false);
      }
    },
  },
  mounted() {
    this.editor = new Editor({
      content: this.modelValue || '',
      extensions: [
        StarterKit.configure({
          heading: false,
          bulletList: false,
          orderedList: false,
          listItem: false,
          blockquote: false,
          codeBlock: false,
          horizontalRule: false,
        }),
        Underline,
        Link.configure({ openOnClick: false, autolink: false, HTMLAttributes: { rel: 'noopener', target: '_blank' } }),
        FontSize,
        TextColor,
      ],
      editorProps: {
        attributes: { class: 'tiptap-editor focus:outline-none' },
      },
      onUpdate: ({ editor }) => {
        this.$emit('update:modelValue', editor.getHTML());
      },
      onSelectionUpdate: () => {
        // Force re-render so the toolbar active states reflect the caret.
        this.$forceUpdate();
      },
    });
  },
  beforeUnmount() {
    this.editor?.destroy();
  },
  methods: {
    promptLink() {
      const prev = this.editor.getAttributes('link').href || '';
      const url = window.prompt('Enter link URL (leave empty to remove):', prev);
      if (url === null) return;
      if (url === '') {
        this.editor.chain().focus().extendMarkRange('link').unsetLink().run();
        return;
      }
      this.editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
    },
    setSize(size) {
      const chain = this.editor.chain().focus();
      if (!size) {
        chain.unsetMark('fontSize').run();
        return;
      }
      chain.setMark('fontSize', { size }).run();
    },
    setColor(color) {
      const chain = this.editor.chain().focus();
      if (!color) {
        chain.unsetMark('textColor').run();
        return;
      }
      chain.setMark('textColor', { color }).run();
    },
  },
};
</script>

<style scoped>
:deep(.tiptap-editor) {
  min-height: 2rem;
}
:deep(.tiptap-editor p) {
  margin: 0 0 0.5rem 0;
}
:deep(.tiptap-editor p:last-child) {
  margin-bottom: 0;
}
:deep(.tiptap-editor a) {
  color: rgb(232 62 109);
  text-decoration: underline;
}
</style>
