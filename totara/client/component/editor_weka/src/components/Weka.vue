<!--
  This file is part of Totara Enterprise Extensions.

  Copyright (C) 2020 onwards Totara Learning Solutions LTD

  Totara Enterprise Extensions is provided only to Totara
  Learning Solutions LTD's customers and partners, pursuant to
  the terms and conditions of a separate agreement with Totara
  Learning Solutions LTD or its affiliate.

  If you do not have an agreement with Totara Learning Solutions
  LTD, you may not access, use, modify, or distribute this software.
  Please contact [licensing@totaralearning.com] for more information.

  @author Simon Chester <simon.chester@totaralearning.com>
  @module editor_weka
-->

<template>
  <div :id="uid" v-focus-within class="tui-weka no-yui-ids">
    <Toolbar v-if="showToolbar" :items="toolbarItems" />
    <div
      ref="editorHost"
      class="tui-weka__editorHost tui-rendered"
      :data-placeholder="placeholder"
    />

    <div ref="extras">
      <!-- This is where modals/custom components will be added/removed -->
    </div>

    <div ref="extrasLive" aria-live="polite" role="status">
      <!-- This is where live-updated UI will be added/removed -->
    </div>
  </div>
</template>

<script>
import tui from 'tui/tui';
import { throttle } from 'tui/util';
import pending from 'tui/pending';
import Editor from '../js/Editor';
import Toolbar from 'editor_weka/components/toolbar/Toolbar';
import { loadLangStrings } from 'tui/i18n';
import editorWeka from 'editor_weka/graphql/weka';
import editorWekaNoSession from 'editor_weka/graphql/weka_nosession';
import FileStorage from '../js/helpers/file';
import WekaValue from '../js/WekaValue';
import { createImmutablePropWatcher } from 'tui/vue_util';

const propEqual = (val, old) => JSON.stringify(old) == JSON.stringify(val);
const warnChange = prop => createImmutablePropWatcher('Weka', prop, propEqual);

export default {
  components: {
    Toolbar,
  },

  props: {
    placeholder: {
      type: String,
      default: '',
    },
    /**
     * This property had been deprecated, please use usageIdentifier instead.
     * @deprecated since Totara 13.3
     */
    instanceId: [Number, String],
    fileItemId: [Number, String],
    /**
     * Sometimes instance id might not be set, because weka editor is used to create a new instance.
     * Therefore, this prop context id is a fallback option to replace instance's id.
     *
     * Note that this contextId will be the same as options.context_id. The reason that we don't
     * pass "context_id" via "options" was because that internally weka will look up to "options"
     * prop and decide whether to fetch from the server or not.
     */
    contextId: [Number, String],
    /**
     * This property had been deprecated, please use usageIdentifier instead.
     * @deprecated since Totara 13.3
     */
    component: String,
    /**
     * This property had been deprecated, please use usageIdentifier instead.
     * @deprecated since Totara 13.3
     */
    area: String,
    usageIdentifier: {
      type: Object,
      validator: prop => 'component' in prop && 'area' in prop,
      default() {
        // Backward compatible.
        return {
          component: this.component || 'editor_weka',
          area: this.area || 'learn',
          instanceId: this.instanceId,
        };
      },
    },
    variant: String,
    /**
     * @value {
     *   context_id: Number
     *   extensions: Object[]
     * }
     */
    options: {
      type: Object,
      validator: prop => prop.extensions !== undefined,
    },
    value: WekaValue,
    /**
     * The compact mode is to determine whether we are showing the tool bar or not.
     */
    compact: Boolean,
    /**
     * If false, loads the editor without a user logged in and disables file support.
     */
    isLoggedIn: {
      type: Boolean,
      default: true,
    },
    /**
     * An array which the result of its JSON_ENCODED is equal to the example below:
     * "
     *  [
     *    {
     *      "name": "unique_valid_extension_name_zero"
     *    },
     *    {
     *      "name": "unique_valid_extension_name_one",
     *      "options": {
     *        "argument_1": "argument_1_value"
     *      }
     *    }
     *  ]
     * "
     */
    extraExtensions: {
      type: Array,
      validator(extensions) {
        return extensions.every(extension => {
          // The key 'name' must exist in the extension object.
          // The key 'options' is optional for it to appear in the extension object.
          return 'name' in extension;
        });
      },
    },
    disabled: Boolean,
    ariaLabel: String,
    ariaLabelledby: String,
    ariaDescribedby: String,
    ariaInvalid: String,
  },

  data() {
    return {
      toolbarItems: [],

      /**
       * @deprecated since Totara 13.3
       */
      toolbarEnabled: !this.compact,
    };
  },

  computed: {
    showToolbar() {
      if (this.compact) {
        return false;
      }

      return this.toolbarItems.length > 0;
    },
  },

  watch: {
    value(value) {
      if (this.displayedValue === value) {
        return;
      }
      this.displayedValue = value || WekaValue.empty();
      if (this.editor) {
        this.editor.setValue(this.displayedValue);
      }
    },

    component: warnChange('component'),
    area: warnChange('area'),
    variant: warnChange('variant'),
    instanceId: warnChange('instanceId'),
    options: warnChange('options'),
    extraExtensions: warnChange('extraExtensions'),

    /**
     * @param {Number|String} value
     */
    fileItemId(value) {
      if (this.editor && this.isLoggedIn) {
        this.editor.updateFileItemId(value);
      }
    },

    disabled(value) {
      if (!this.editor) {
        return;
      }
      this.editor.setEditable(!value);
      if (this.editor.view) {
        this.updateToolbar();
        this.editor.forceRerenderView();
      }
    },
  },

  created() {
    this.updateToolbarThrottled = throttle(this.updateToolbar, 100);
    if (this.value) {
      this.displayedValue = this.value;
    } else {
      this.displayedValue = WekaValue.empty();
    }
    this.finalOptions = null;
  },

  mounted() {
    if (this.component) {
      console.warn(
        "The prop 'component' had been deprecated, please use 'usageIdentifier' instead"
      );
    }

    if (this.area) {
      console.warn(
        "The prop 'area' had been deprecated, please use 'usageIdentifier' instead"
      );
    }

    if (this.instanceId) {
      console.warn(
        "The prop 'instanceId' had been deprecated, please use 'usageIdentifier' instead"
      );
    }

    this.createEditor();
  },

  beforeDestroy() {
    if (this.editor) {
      this.editor.destroy();
    }
  },

  methods: {
    /**
     * Setup the editor options such as showing toolbar, the extensions that the editor needs.
     */
    async setupOptions() {
      if (this.options) {
        this.finalOptions = Object.assign({}, this.options);
        return;
      }

      // Start populating the options from the graphql call.
      const result = await this.$apollo.query({
        query: this.isLoggedIn ? editorWeka : editorWekaNoSession,
        fetchPolicy: 'no-cache',
        variables: {
          instance_id: this.usageIdentifier.instanceId,
          component: this.usageIdentifier.component,
          area: this.usageIdentifier.area,
          context_id: this.contextId || undefined,
          extra_extensions: this.extraExtensions
            ? JSON.stringify(this.extraExtensions)
            : undefined,
          variant_name:
            this.variant ||
            `${this.usageIdentifier.component}-${this.usageIdentifier.area}`,
        },
      });

      this.finalOptions = Object.assign({}, result.data.editor);
    },

    /**
     * This function will try to fetch the extensions via HTTP call. This function will not cache the
     * modules that had already been loaded.
     *
     * @return {Promise<[]>}
     */
    async getExtensions() {
      await this.setupOptions();
      let extensions = [];

      if (Array.isArray(this.finalOptions.extensions)) {
        extensions = await Promise.all(
          this.finalOptions.extensions.map(({ tuicomponent, options }) => {
            let opt = {};

            if (options != null) {
              if (typeof options === 'object') {
                opt = options;
              } else {
                opt = JSON.parse(options);
              }
            }

            return tui
              .import(tuicomponent)
              .then(ext => tui.defaultExport(ext)(opt));
          })
        );
      }

      return extensions;
    },

    /**
     * This function had been deprecated and no longer used.
     *
     * @return {Promise}
     * @deprecated since Totara 13.3
     */
    async getRepositoryData() {
      console.warn(
        '[editor_weka] The function getRepositoryData had been deprecated and no longer used.'
      );

      return Promise.resolve(null);
    },

    /**
     * This function had been deprecated and no longer used.
     *
     * @return {Promise}
     * @deprecated since Totara 13.3
     */
    async getCurrentFiles() {
      console.warn(
        '[editor_weka] The function getCurrentFiles had been deprecated and no longer used.'
      );

      return Promise.resolve([]);
    },

    async createEditor() {
      if (this.editor) {
        return;
      }

      let pendingDone = pending('weka');

      const extensions = await this.getExtensions();

      let fileStorage = new FileStorage({
        itemId: this.fileItemId,
        contextId: this.finalOptions.context_id || null,
      });

      this.editor = new Editor({
        value: this.displayedValue,
        placeholder: this.placeholder,
        parent: this,
        viewExtrasEl: this.$refs.extras,
        viewExtrasLiveEl: this.$refs.extrasLive,
        extensions: extensions,
        fileStorage: fileStorage,
        onUpdate: this.$_onUpdate.bind(this),
        contextId: this.finalOptions.context_id || null,
        component: this.usageIdentifier.component || null,
        area: this.usageIdentifier.area || null,
        instanceId: this.usageIdentifier.instanceId || null,
        ariaLabel: this.ariaLabel,
        ariaLabelledby: this.ariaLabelledby,
        ariaDescribedby: this.ariaDescribedby,
        ariaInvalid: this.ariaInvalid,
        editable: () => !this.disabled,
        onTransaction: () => {
          this.updateToolbarThrottled();
        },
        onFocus: e => {
          this.$emit('focus', e);
        },
        onBlur: e => {
          this.$emit('blur', e);
        },
      });

      const components = this.editor.allVueComponents();

      await Promise.all([
        tui.loadRequirements({ components }),
        loadLangStrings(this.editor.allStrings()),
      ]);

      this.view = this.editor.createView(this.$refs.editorHost);

      this.updateToolbar();

      pendingDone();

      // Event emitted to make the parent component knowing that this editor has been mounted properly.
      this.$emit('ready');
    },

    updateToolbar() {
      if (!this.editor.destroyed) {
        this.toolbarItems = this.editor.getToolbarItems();
      }
    },

    /**
     * Emit new value.
     *
     * @param {WekaValue} value
     */
    $_onUpdate(value) {
      this.displayedValue = value;
      this.$emit('input', value);
    },
  },
};
</script>

<style lang="scss">
.tui-weka {
  position: relative;
  display: flex;
  flex-direction: column;
  width: 100%;
  background-color: var(--color-neutral-1);
  border: var(--border-width-thin) solid var(--form-input-border-color);

  &.tui-focusWithin {
    background: var(--form-input-bg-color-focus);
    border: var(--form-input-border-size) solid;
    border-color: var(--form-input-border-color-focus);
    outline: none;
    box-shadow: var(--form-input-shadow-focus);
  }

  &__placeholder {
    // Styling for the place holder.
    &:before {
      color: var(--color-neutral-6);
      content: attr(data-placeholder);
    }
  }

  &__editorHost {
    display: flex;
    flex-direction: column;
    flex-grow: 1;
    overflow: auto;

    > .tui-weka-editor {
      flex-grow: 1;
    }
  }

  .ProseMirror-focused {
    .tui-weka {
      &__placeholder {
        &:before {
          content: '';
        }
      }
    }
  }

  .ProseMirror {
    padding: var(--gap-4);
    white-space: pre-wrap;
    white-space: break-spaces;
    word-wrap: break-word;
    -webkit-font-variant-ligatures: none;
    font-variant-ligatures: none;
    font-feature-settings: 'liga' 0; /* the above doesn't seem to work in Edge */

    &:focus {
      outline: none;
    }

    hr {
      margin: 0 0 var(--gap-2) 0;
    }

    pre {
      white-space: pre-wrap;
    }

    ol,
    ul {
      margin: 0;
      padding-left: var(--gap-4);
    }

    ul ul {
      list-style-type: circle;
    }
    ul ul ul {
      list-style-type: square;
    }

    li {
      position: relative;
    }
  }

  .ProseMirror-hideselection *::selection,
  .ProseMirror-hideselection *::-moz-selection {
    background: transparent;
  }

  .ProseMirror-hideselection {
    caret-color: transparent;
  }

  .ProseMirror-selectednode {
    outline: var(--border-width-normal) solid var(--color-secondary);
  }

  /* Make sure li selections wrap around markers */

  li.ProseMirror-selectednode {
    outline: none;
  }

  li.ProseMirror-selectednode:after {
    position: absolute;
    top: -2px;
    right: -2px;
    bottom: -2px;
    left: -32px;
    border: var(--border-width-normal) solid var(--color-secondary);
    content: '';
    pointer-events: none;
  }

  .ProseMirror-gapcursor {
    position: relative;
  }

  .ProseMirror-gapcursor:before {
    // insert an nbsp to make gapcursor expand to full line height
    content: '\00a0';
  }

  // Add a 'fake' blinking cursor to the gapcursor element
  .ProseMirror-gapcursor:after {
    position: absolute;
    top: -2px;
    display: block;
    height: 20px;
    border-left: 1px solid black;
    animation: ProseMirror-cursor-blink 1.1s steps(2, start) infinite;
    content: '';
  }

  @keyframes ProseMirror-cursor-blink {
    to {
      visibility: hidden;
    }
  }
}
</style>
