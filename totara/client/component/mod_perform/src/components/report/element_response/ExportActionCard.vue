<!--
  This file is part of Totara Enterprise Extensions.

  Copyright (C) 2020 onwards Totara Learning Solutions LTD

  Totara Enterprise Extensions is provided only to Totara
  Learning Solutions LTD’s customers and partners, pursuant to
  the terms and conditions of a separate agreement with Totara
  Learning Solutions LTD or its affiliate.

  If you do not have an agreement with Totara Learning Solutions
  LTD, you may not access, use, modify, or distribute this software.
  Please contact [licensing@totaralearning.com] for more information.

  @author Jaron Steenson <jaron.steenson@totaralearning.com>
  @module mod_perform
-->

<template>
  <ActionCard class="tui-elementResponseReportingActionCard" :has-shadow="true">
    <template v-slot:card-body>
      <span v-html="countString" />
    </template>
    <template v-slot:card-action>
      <ButtonIcon
        :aria-label="$str('export_all', 'mod_perform')"
        :styleclass="{
          primary: true,
          small: true,
        }"
        :text="$str('export_all', 'mod_perform')"
        @click="tryConfirmExport"
      >
        <DownloadIcon />
      </ButtonIcon>

      <ExportConfirmModal
        :open="exportConfirmModal"
        :export-href="exportHref"
        @request-close="closeExportConfirmModal"
      />

      <ModalPresenter
        :open="exportLimitExceededModal"
        @request-close="closeExportLimitExceededModal"
      >
        <Modal
          :aria-labelledby="$id('question-element-preview-modal')"
          class="tui-elementResponseReportingExportConfirmModal"
          size="normal"
        >
          <ModalContent
            :title="$str('export_limit_exceeded_modal_title', 'mod_perform')"
            :title-id="$id('element-response-export-limit-exceeded-modal')"
            close-button
          >
            <p>
              {{
                $str(
                  'export_limit_exceeded_modal_text',
                  'mod_perform',
                  exportRowLimit
                )
              }}
            </p>

            <template v-slot:buttons>
              <Button
                :text="$str('close', 'totara_core')"
                @click="closeExportLimitExceededModal"
              />
            </template>
          </ModalContent>
        </Modal>
      </ModalPresenter>
    </template>
  </ActionCard>
</template>

<script>
import ActionCard from 'tui/components/card/ActionCard';
import Button from 'tui/components/buttons/Button';
import ButtonIcon from 'tui/components/buttons/ButtonIcon';
import DownloadIcon from 'tui/components/icons/Download';
import ExportConfirmModal from 'mod_perform/components/report/element_response/ExportConfirmModal';
import Modal from 'tui/components/modal/Modal';
import ModalContent from 'tui/components/modal/ModalContent';
import ModalPresenter from 'tui/components/modal/ModalPresenter';

export default {
  components: {
    ActionCard,
    Button,
    ButtonIcon,
    DownloadIcon,
    ExportConfirmModal,
    Modal,
    ModalContent,
    ModalPresenter,
  },
  props: {
    additionalExportHrefParams: {
      type: Object,
      required: true,
    },
    rowCount: {
      type: Number,
      required: true,
    },
    embeddedShortname: {
      type: String,
      required: true,
    },
    filterHash: {
      type: String,
      required: true,
    },
    exportRowLimit: {
      type: Number,
      required: true,
    },
  },
  data() {
    return {
      exportConfirmModal: false,
      exportLimitExceededModal: false,
    };
  },
  computed: {
    exportRowLimitExceeded() {
      return this.rowCount > this.exportRowLimit;
    },
    exportHref() {
      if (this.exportRowLimitExceeded) {
        return null;
      }

      let params = this.additionalExportHrefParams;
      Object.assign(params, {
        action: 'bulk',
        export: 'Export',
        format: 'csv',
        filtered_report_export_type: this.embeddedShortname,
        filtered_report_filter_hash: this.filterHash,
      });
      return this.$url('/mod/perform/reporting/performance/export.php', params);
    },
    countString() {
      if (this.rowCount === 1) {
        return this.$str('x_record_selected', 'mod_perform', this.rowCount);
      }

      return this.$str('x_records_selected', 'mod_perform', this.rowCount);
    },
  },
  methods: {
    tryConfirmExport() {
      if (this.exportRowLimitExceeded) {
        this.openExportLimitExceededModal();
      } else {
        this.openExportConfirmModal();
      }
    },
    openExportConfirmModal() {
      this.exportConfirmModal = true;
    },
    closeExportConfirmModal() {
      this.exportConfirmModal = false;
    },
    openExportLimitExceededModal() {
      this.exportLimitExceededModal = true;
    },
    closeExportLimitExceededModal() {
      this.exportLimitExceededModal = false;
    },
  },
};
</script>

<lang-strings>
{
  "mod_perform": [
    "button_cancel",
    "export",
    "export_all",
    "export_confirm_modal_title",
    "export_confirm_modal_text",
    "export_limit_exceeded_modal_title",
    "export_limit_exceeded_modal_text",
    "x_record_selected",
    "x_records_selected"
  ],
  "totara_core": [
    "close"
  ]
}
</lang-strings>

<style lang="scss">
.tui-elementResponseReportingActionCard {
  margin-bottom: var(--gap-10);
}
</style>
