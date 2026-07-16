# Centralised notifications core system

## Permissions
### How to provide extra capabilities for accessing notifications.
Adding extra capabilities to manage notifications at the plugin level that integrate with centralised
notifications is a very generic way to allow any user to see the link in the navigation tree and access it.

To add the extra capabilities within the plugin level, all we have to do is to introduce a new file under 
the `db` directory. For example, if we are introducing extra capabilities for plugin `totara_program` 
then a file `server/totara/program/db/notification_access.php` should be created with the content as below:

```php
// File: server/totara/program/db/notification_access.php
defined('MOODLE_INTERNAL') || die();

$accesses = [
    [
        'capability' => 'totara/program:manage_notification',
        'context_levels' => [
            CONTEXT_SYSTEM,
            CONTEXT_COURSECAT,
            CONTEXT_PROGRAM        
        ]
    ]
];
```

### How to implement custom permissions checks at the plugin level.
The base notification resolver does not provide any custom permission checks. Instead, the system, by default, 
checks the generic capability `totara/notification:managenotifications`. However, if a notification resolver 
wishes to perform a custom checks then all it needs to do is to implement the provided interface for it which 
is `\totara_notification\resolver\abstraction\permission_resolver`. Then the function provided
by the resolver that implements the interface will be invoked.

The logic of performing premissions check is in order below:
+ If the user has the capability `totara/notification:managenotifications` then user is able to interact with notification.
+ If the resolver has a callback for custom permissions checks, and it results in TRUE then user is able to interact with notification 
  preference, otherwise if FALSE then the user is not able to interact with the notification.
+ If the resolver does not have the callback then the result is FALSE for the whole process and user is not able to interact with notification.

Example for totara_program:

```php

use totara_core\extended_context;
use totara_notification\resolver\abstraction\permission_resolver;
use totara_notification\resolver\notifiable_event_resolver;

class totara_program_resolver extends notifiable_event_resolver implements permission_resolver {
    // Whatever functions that are required from notifiable_event_resolver go here.
    
    /**
     * @param extended_context $context
     * @param int $user_id
     * @return bool
     */
    public static function can_user_manage_notification_preferences(extended_context $context,int $user_id) : bool{
        $natural_context = $context->get_context();
        return has_capability('totara/program:manage_notification', $natural_context, $user_id);
    }
}
```