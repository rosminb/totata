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

  @author Kian Nguyen <kian.nguyen@totaralearning.com>
  @module engage_article
-->
<template>
  <div class="tui-engageArticleContent">
    <InlineEditing
      v-show="!editing"
      :button-aria-label="$str('editarticlecontent', 'engage_article', title)"
      :full-width="true"
      :restricted-mode="true"
      :update-able="updateAble"
      @click="editing = true"
    >
      <div
        slot="content"
        ref="content"
        class="tui-engageArticleContent__content"
        v-html="content"
      />
    </InlineEditing>
    <EditArticleForm
      v-if="editing"
      :resource-id="resourceId"
      :submitting="submitting"
      @submit="updateArticle"
      @cancel="editing = false"
    />
  </div>
</template>

<script>
import InlineEditing from 'totara_engage/components/form/InlineEditing';
import EditArticleForm from 'engage_article/components/form/EditArticleContentForm';
import tui from 'tui/tui';

// GraphQL queries
import updateArticle from 'engage_article/graphql/update_article';
import getArticle from 'engage_article/graphql/get_article';

export default {
  components: {
    InlineEditing,
    EditArticleForm,
  },

  props: {
    /**
     * For fetching the draft content of article.
     */
    resourceId: {
      type: [String, Number],
      required: true,
    },

    title: {
      type: String,
      required: true,
    },

    content: {
      type: String,
      required: true,
    },

    updateAble: {
      type: Boolean,
      required: true,
    },
  },

  data() {
    return {
      editing: false,
      submitting: false,
    };
  },
  mounted() {
    this.$_scan();
  },

  updated() {
    this.$_scan();
  },

  methods: {
    $_scan() {
      this.$nextTick().then(() => {
        let content = this.$refs.content;
        if (!content) {
          return;
        }

        tui.scan(content);
      });
    },

    /**
     *
     * @param {String} content
     * @param {Number} format
     */
    async updateArticle({ content, format, itemId }) {
      this.submitting = true;

      try {
        await this.$apollo.mutate({
          mutation: updateArticle,
          variables: {
            resourceid: this.resourceId,
            content: content,
            format: format,
            draft_id: itemId,
          },

          /**
           *
           * @param {DataProxy} proxy
           * @param {Object} data
           */
          updateQuery: (proxy, data) => {
            proxy.writeQuery({
              query: getArticle,
              variables: {
                resourceid: this.resourceId,
              },

              data: data,
            });
          },
        });
        this.editing = false;
      } finally {
        this.submitting = false;
      }
    },
  },
};
</script>

<lang-strings>
  {
    "engage_article": [
      "editarticlecontent"
    ]
  }
</lang-strings>

<style lang="scss">
.tui-engageArticleContent {
  &__content {
    flex-grow: 1;
    width: 100%;
    .tui-rendered > p {
      @include tui-wordbreak--hard;
    }
  }
}
</style>
