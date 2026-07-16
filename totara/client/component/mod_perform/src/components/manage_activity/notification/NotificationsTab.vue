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

  @author Tatsuhiro Kirihara <tatsuhiro.kirihara@totaralearning.com>
  @module mod_perform
-->

<template>
  <Loader :loading="$apollo.loading || isLoading">
    <div class="tui-activityNotifications">
      <h3 class="tui-activityNotifications__header">
        <span class="tui-activityNotifications__title">
          {{ $str('manage_activities_tabs_notifications', 'mod_perform') }}
        </span>
      </h3>

      <div class="tui-activityNotifications__toggleLabel" aria-hidden="true">
        <span class="tui-activityNotifications__toggleLabel-active">
          {{ $str('notification_active', 'mod_perform') }}
        </span>
      </div>

      <NotificationSection
        v-for="notification in notifications"
        :key="notification.class_key"
        :data="notification"
        @toggleNotification="onToggleNotification"
        @toggleRecipient="onToggleRecipient"
        @updateTriggers="onUpdateTriggers"
      />
    </div>
  </Loader>
</template>

<script>
import { notify } from 'tui/notifications';
import Loader from 'tui/components/loading/Loader';
import NotificationSection from 'mod_perform/components/manage_activity/notification/NotificationSection';
import notificationsQuery from 'mod_perform/graphql/notifications';
import toggleNotificationMutation from 'mod_perform/graphql/toggle_notification';
import toggleNotificationRecipientMutation from 'mod_perform/graphql/toggle_notification_recipient';
import updateNotificationTriggersMutation from 'mod_perform/graphql/update_notification_triggers';
import { debounce } from 'tui/util';

export default {
  components: {
    Loader,
    NotificationSection,
  },

  props: {
    value: {
      type: Object,
      required: true,
    },
    tabIsActive: {
      type: Boolean,
      required: true,
    },
  },

  data() {
    return {
      notifications: [],
      isLoading: false,
      activityUpdated: false,
      skipQuery: true,
    };
  },

  apollo: {
    notifications: {
      query: notificationsQuery,
      variables() {
        return { activity_id: this.value.id };
      },
      update: data => data.mod_perform_notifications,
      skip() {
        return this.skipQuery && !this.tabIsActive;
      },
      fetchPolicy: 'network-only',
    },
  },

  watch: {
    value() {
      // Queue the query to be updated again if other activity settings change
      this.activityUpdated = true;
    },
    tabIsActive() {
      this.skipQuery = false;
      // We only refetch the notification data if the activity has changed, and this tab is selected again.
      if (this.activityUpdated && this.tabIsActive) {
        this.$apollo.queries.notifications.refetch();
        this.activityUpdated = false;
      }
    },
  },

  methods: {
    onUpdateTriggers: debounce(
      async function(section, triggers) {
        try {
          // We deliberately don't update the notification state with what is returned by the mutation,
          // as otherwise it creates a weird effect because of the 500ms delay.
          await this.$apollo.mutate({
            mutation: updateNotificationTriggersMutation,
            variables: {
              input: {
                notification_id: section.id,
                values: triggers,
              },
            },
          });
          this.successBanner();
        } catch (ex) {
          this.errorBanner();
          throw ex;
        }
      },
      500,
      { leading: true, trailing: true }
    ),

    /**
     * Trigger the mutation when a collapsible header toggle is clicked
     *
     */
    async onToggleNotification(section, active) {
      this.isLoading = true;

      try {
        const { data: result } = await this.$apollo.mutate({
          mutation: toggleNotificationMutation,
          variables: {
            input: {
              notification_id: section.id,
              active,
            },
          },
        });

        this.updateNotification(result.mod_perform_toggle_notification);
        this.successBanner();
        this.isLoading = false;
      } catch (e) {
        this.errorBanner();
        this.isLoading = false;
      }
    },

    /**
     * @deprecated since Totara 13.2
     */
    async createNotificationIfNotExists(section) {
      console.warn(
        '[NotificationsTab] createNotificationIfNotExists() is deprecated and should not be used.\n' +
          'Notifications now always exist, so this will always return the ID of the notification.'
      );
      return section.id;
    },

    /**
     * Trigger the mutation when a participant selection recipient toggle is clicked
     *
     */
    async onToggleRecipient(section, recipient) {
      this.isLoading = true;

      try {
        const { data: result } = await this.$apollo.mutate({
          mutation: toggleNotificationRecipientMutation,
          variables: {
            input: {
              notification_id: section.id,
              relationship_id: recipient.id,
              active: recipient.active,
            },
          },
        });
        this.updateNotification(
          result.mod_perform_toggle_notification_recipient
        );
        this.successBanner();
        this.isLoading = false;
      } catch (e) {
        this.errorBanner();
        this.isLoading = false;
      }
    },

    /**
     * Display the error banner
     *
     */
    errorBanner() {
      notify({
        message: this.$str('toast_error_generic_update', 'mod_perform'),
        type: 'error',
      });
    },

    /**
     * Display the success banner
     *
     */
    successBanner() {
      notify({
        message: this.$str('toast_success_activity_update', 'mod_perform'),
        type: 'success',
      });
    },

    updateNotification(updatedNotification) {
      this.notifications = this.notifications.map(notification => {
        if (
          notification.class_key === updatedNotification.notification.class_key
        ) {
          return updatedNotification.notification;
        } else {
          return notification;
        }
      });
    },
  },
};
</script>

<lang-strings>
  {
    "mod_perform": [
      "notification_active",
      "manage_activities_tabs_notifications",
      "toast_error_generic_update",
      "toast_success_activity_update"
    ]
  }
</lang-strings>

<style lang="scss">
.tui-activityNotifications {
  & > * + * {
    margin-top: var(--gap-2);
  }

  &__header {
    display: flex;
    margin: 0;
  }

  &__title {
    @include tui-font-heading-small();
  }

  &__toggleLabel {
    display: flex;
    margin-top: var(--gap-4);

    &-active {
      @include tui-font-heading-label-small();
      margin: 0 var(--gap-4) 0 auto;
    }
  }
}
</style>
