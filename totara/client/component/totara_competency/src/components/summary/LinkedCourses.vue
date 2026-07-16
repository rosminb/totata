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

  @author Mark Metcalfe <mark.metcalfe@totaralearning.com>
  @module totara_competency
-->

<template>
  <div class="tui-competencyOverviewLinkedCourses">
    <div class="tui-competencySummary__sectionHeader">
      <h3 class="tui-competencySummary__sectionHeader-title">
        {{ $str('linked_courses', 'totara_competency') }}
      </h3>
      <a
        :href="editUrl"
        class="tui-competencySummary__sectionHeader-edit"
        :title="$str('edit', 'core')"
      >
        <EditIcon :size="200" :alt="$str('edit', 'core')" />
      </a>
    </div>

    <Table
      :data="data"
      :border-bottom-hidden="true"
      :border-top-hidden="true"
      :border-separator-hidden="true"
      :hover-off="true"
      :no-items-text="$str('no_courses_linked_yet', 'totara_competency')"
    >
      <template v-slot:row="{ row }">
        <Cell size="6" valign="center">
          <a :href="courseUrl(row.course_id)">
            {{ row.fullname }}
          </a>
        </Cell>
        <Cell size="10" valign="center">
          <span>
            {{
              $str(
                row.is_mandatory ? 'mandatory' : 'optional',
                'totara_competency'
              )
            }}
          </span>
        </Cell>
      </template>
    </Table>
  </div>
</template>

<script>
// Components
import Cell from 'tui/components/datatable/Cell';
import EditIcon from 'tui/components/icons/Edit';
import Table from 'tui/components/datatable/Table';
// Query
import linkedCoursesQuery from 'totara_competency/graphql/linked_courses';

export default {
  components: {
    Cell,
    EditIcon,
    Table,
  },

  props: {
    competencyId: {
      required: true,
      type: Number,
    },
  },

  data: function() {
    return {
      data: [],
      editUrl: '',
    };
  },

  apollo: {
    data: {
      query: linkedCoursesQuery,
      variables() {
        return {
          competency_id: this.competencyId,
        };
      },
      update({ totara_competency_linked_courses: data }) {
        this.editUrl = this.$url('/totara/competency/competency_edit.php', {
          s: 'linkedcourses',
          id: this.competencyId,
        });
        return data;
      },
    },
  },

  methods: {
    courseUrl(course_id) {
      return this.$url('/course/view.php', {
        id: course_id,
      });
    },
  },
};
</script>

<lang-strings>
  {
    "core": [
      "edit"
    ],
    "totara_competency": [
      "linked_courses",
      "mandatory",
      "no_courses_linked_yet",
      "optional"
    ]
  }
</lang-strings>
