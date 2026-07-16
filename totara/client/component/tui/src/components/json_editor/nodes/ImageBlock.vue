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
  @module totara_core
-->

<template>
  <div class="tui-imageBlock">
    <ResponsiveImage :src="url" :alt="altText" />
  </div>
</template>

<script>
import ResponsiveImage from 'tui/components/images/ResponsiveImage';

export default {
  components: {
    ResponsiveImage,
  },

  inheritAttrs: false,

  props: {
    filename: {
      type: String,
      required: true,
    },

    url: {
      type: String,
      required: true,
    },

    altText: {
      type: String,
      default: '',
    },
  },

  computed: {
    /** @deprecated since 13.3 */
    attributes: () => null,
  },
};
</script>

<style lang="scss">
.tui-imageBlock {
  margin: var(--gap-8) 0;
}
</style>
