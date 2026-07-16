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

  @author Riana Rossouw <riana.rossouw@totaralearning.com>
  @module totara_competency
-->

<template>
  <div class="tui-competencySummary">
    <div class="tui-competencySummary__header">
      <NotificationBanner
        v-if="achievementPathWarningMessage"
        type="warning"
        :message="achievementPathWarningMessage"
      />
      <a :href="backLinkUrl" class="tui-competencySummary__header-backLink">
        {{ $str('back_to', 'totara_competency', frameworkName) }}
      </a>
    </div>

    <div class="tui-competencySummary__content">
      <PageHeading
        :title="
          $str('competency_title', 'totara_hierarchy', {
            framework: frameworkName,
            fullname: competencyName,
          })
        "
      />

      <General :competency-id="competencyId" />
      <LinkedCourses :competency-id="competencyId" />
      <AchievementConfiguration
        v-if="performEnabled"
        :competency-id="competencyId"
      />
    </div>
  </div>
</template>

<script>
import AchievementConfiguration from 'totara_competency/components/summary/AchievementConfiguration';
import General from 'totara_competency/components/summary/CompetencySummaryGeneral';
import LinkedCourses from 'totara_competency/components/summary/LinkedCourses';
import NotificationBanner from 'tui/components/notifications/NotificationBanner';
import PageHeading from 'tui/components/layouts/PageHeading';

export default {
  components: {
    AchievementConfiguration,
    General,
    LinkedCourses,
    NotificationBanner,
    PageHeading,
  },

  props: {
    achievementPathWarningMessage: {
      type: String,
      required: true,
    },
    competencyId: {
      type: Number,
      required: true,
    },
    competencyName: {
      type: String,
      required: true,
    },
    frameworkId: {
      type: Number,
      required: true,
    },
    frameworkName: {
      type: String,
      required: true,
    },
    performEnabled: {
      type: Boolean,
      required: true,
    },
  },

  computed: {
    backLinkUrl() {
      return this.$url('/totara/hierarchy/index.php', {
        prefix: 'competency',
        frameworkid: this.frameworkId,
      });
    },
  },
};
</script>

<lang-strings>
  {
    "totara_competency": [
      "back_to"
    ],
    "totara_hierarchy": [
      "competency_title"
    ]
  }
</lang-strings>

<style lang="scss">
.tui-competencySummary {
  @include tui-font-body();

  & > * + * {
    margin-top: var(--gap-2);
  }

  &__header {
    & > * + * {
      margin-top: var(--gap-8);
    }

    &-backLink {
      display: inline-block;
    }
  }

  &__content {
    & > * + * {
      margin-top: var(--gap-4);
    }
  }

  &__sectionHeader {
    display: flex;
    align-items: center;
    padding-bottom: var(--gap-1);
    border-bottom: var(--border-width-thin) solid var(--color-neutral-5);

    &-title {
      margin: 0;
      @include tui-font-heading-small();
    }

    &-edit {
      margin-left: auto;
      padding-left: var(--gap-2);
    }
  }
}
</style>
